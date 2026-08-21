#include <curl/curl.h>
#include <cjson/cJSON.h>
#include <openssl/evp.h>
#include <openssl/ec.h>
#include <openssl/pem.h>
#include <openssl/rand.h>
#include <openssl/sha.h>

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

/* Licora Secure API v2 lifecycle reference.
 * Dependencies: libcurl, OpenSSL 3.x, cJSON.
 * The test device is ephemeral and is deactivated before exit.
 */

typedef struct { char *data; size_t len; } Buffer;

typedef struct {
    char base[1024];
    char app_id[128];
    char app_version[65];
    char device_id[128];
    char *public_pem;
    EVP_PKEY *device_key;
} LicoraClient;

static void die(const char *message) { fprintf(stderr, "%s\n", message); exit(1); }

static size_t write_cb(char *ptr, size_t size, size_t nmemb, void *userdata) {
    size_t bytes = size * nmemb; Buffer *b = (Buffer *)userdata;
    char *next = (char *)realloc(b->data, b->len + bytes + 1); if (!next) return 0;
    b->data = next; memcpy(b->data + b->len, ptr, bytes); b->len += bytes; b->data[b->len] = '\0'; return bytes;
}

static char *b64url(const unsigned char *data, size_t size) {
    size_t cap = 4 * ((size + 2) / 3) + 1; char *out = (char *)malloc(cap); if (!out) return NULL;
    int n = EVP_EncodeBlock((unsigned char *)out, data, (int)size); out[n] = '\0';
    for (int i=0;i<n;i++) { if (out[i]=='+') out[i]='-'; else if (out[i]=='/') out[i]='_'; }
    while (n>0 && out[n-1]=='=') out[--n]='\0'; return out;
}

static unsigned char *b64url_decode(const char *input, size_t *out_len) {
    size_t len = strlen(input), padded = ((len + 3) / 4) * 4; char *tmp = (char *)malloc(padded + 1); if (!tmp) return NULL;
    memcpy(tmp, input, len); for (size_t i=0;i<len;i++){ if(tmp[i]=='-')tmp[i]='+'; else if(tmp[i]=='_')tmp[i]='/'; }
    for(size_t i=len;i<padded;i++) tmp[i]='='; tmp[padded]='\0';
    unsigned char *out=(unsigned char *)malloc((padded/4)*3+1); if(!out){free(tmp);return NULL;}
    int n=EVP_DecodeBlock(out,(unsigned char*)tmp,(int)padded); size_t pad=padded>=2&&tmp[padded-2]=='='?2:(padded&&tmp[padded-1]=='='?1:0); free(tmp);
    if(n<0){free(out);return NULL;} *out_len=(size_t)n-pad; out[*out_len]='\0'; return out;
}

static void sha256_hex(const unsigned char *data, size_t len, char out[65]) {
    unsigned char digest[SHA256_DIGEST_LENGTH]; SHA256(data,len,digest);
    for(int i=0;i<SHA256_DIGEST_LENGTH;i++) sprintf(out+i*2,"%02x",digest[i]); out[64]='\0';
}

static const char *url_path(const char *url) {
    const char *p=strstr(url,"://"); p=p?strchr(p+3,'/'):strchr(url,'/'); return p?p:"/";
}

static EVP_PKEY *generate_p256(void) {
    EVP_PKEY_CTX *ctx=EVP_PKEY_CTX_new_id(EVP_PKEY_EC,NULL); EVP_PKEY *key=NULL; if(!ctx) return NULL;
    if(EVP_PKEY_keygen_init(ctx)<=0 || EVP_PKEY_CTX_set_ec_paramgen_curve_nid(ctx,NID_X9_62_prime256v1)<=0 || EVP_PKEY_keygen(ctx,&key)<=0){EVP_PKEY_CTX_free(ctx);return NULL;}
    EVP_PKEY_CTX_free(ctx); return key;
}

static char *public_pem(EVP_PKEY *key) {
    BIO *bio=BIO_new(BIO_s_mem()); if(!bio || PEM_write_bio_PUBKEY(bio,key)!=1){if(bio)BIO_free(bio);return NULL;}
    BUF_MEM *mem=NULL; BIO_get_mem_ptr(bio,&mem); char *out=(char*)malloc(mem->length+1); if(out){memcpy(out,mem->data,mem->length);out[mem->length]='\0';} BIO_free(bio); return out;
}

static char *sign_canonical(EVP_PKEY *key,const char *canonical) {
    EVP_MD_CTX *ctx=EVP_MD_CTX_new(); size_t len=0; unsigned char *sig=NULL; char *encoded=NULL; if(!ctx)return NULL;
    if(EVP_DigestSignInit(ctx,NULL,EVP_sha256(),NULL,key)<=0 || EVP_DigestSignUpdate(ctx,canonical,strlen(canonical))<=0 || EVP_DigestSignFinal(ctx,NULL,&len)<=0)goto done;
    sig=(unsigned char*)malloc(len); if(!sig)goto done; if(EVP_DigestSignFinal(ctx,sig,&len)<=0)goto done; encoded=b64url(sig,len);
 done: free(sig); EVP_MD_CTX_free(ctx); return encoded;
}

static char *jwt_jti(const char *token) {
    const char *a=strchr(token,'.'); if(!a)return NULL; const char *b=strchr(a+1,'.'); if(!b)return NULL;
    size_t seg=(size_t)(b-a-1); char *segment=(char*)malloc(seg+1); memcpy(segment,a+1,seg);segment[seg]='\0'; size_t raw_len=0; unsigned char *raw=b64url_decode(segment,&raw_len); free(segment); if(!raw)return NULL;
    cJSON *json=cJSON_Parse((char*)raw); free(raw); if(!json)return NULL; cJSON *jti=cJSON_GetObjectItemCaseSensitive(json,"jti"); char *out=cJSON_IsString(jti)?strdup(jti->valuestring):NULL; cJSON_Delete(json); return out;
}

static char *json_string(cJSON *object) { char *raw=cJSON_PrintUnformatted(object); if(!raw)die("JSON encoding failed"); return raw; }

static cJSON *post_json(LicoraClient *client,const char *name,cJSON *payload,const char *context,const char *access_token) {
    char url[1400]; snprintf(url,sizeof(url),"%s/api/v2/%s.php",client->base,name); char *body=json_string(payload); char body_hash[65]; sha256_hex((unsigned char*)body,strlen(body),body_hash);
    unsigned char nonce_raw[18]; if(RAND_bytes(nonce_raw,sizeof(nonce_raw))!=1)die("RAND_bytes failed"); char *nonce=b64url(nonce_raw,sizeof(nonce_raw)); long long ts=(long long)time(NULL);
    const char *path=url_path(url); size_t path_len=strcspn(path,"?#"); char *clean_path=strndup(path,path_len); size_t canon_len=strlen(clean_path)+strlen(nonce)+strlen(body_hash)+strlen(context)+80; char *canonical=(char*)malloc(canon_len);
    snprintf(canonical,canon_len,"POST\n%s\n%lld\n%s\n%s\n%s",clean_path,ts,nonce,body_hash,context); char *signature=sign_canonical(client->device_key,canonical); if(!signature)die("device proof signing failed");
    CURL *curl=curl_easy_init(); if(!curl)die("curl_easy_init failed"); struct curl_slist *headers=NULL; char h1[128],h2[256],h3[2048]; snprintf(h1,sizeof(h1),"X-Licora-Timestamp: %lld",ts); snprintf(h2,sizeof(h2),"X-Licora-Nonce: %s",nonce); snprintf(h3,sizeof(h3),"X-Licora-Device-Signature: %s",signature);
    headers=curl_slist_append(headers,"Content-Type: application/json"); headers=curl_slist_append(headers,h1); headers=curl_slist_append(headers,h2); headers=curl_slist_append(headers,h3); char *auth=NULL; if(access_token&&*access_token){auth=(char*)malloc(strlen(access_token)+24);sprintf(auth,"Authorization: Bearer %s",access_token);headers=curl_slist_append(headers,auth);} Buffer response={calloc(1,1),0};
    curl_easy_setopt(curl,CURLOPT_URL,url); curl_easy_setopt(curl,CURLOPT_POST,1L); curl_easy_setopt(curl,CURLOPT_POSTFIELDS,body); curl_easy_setopt(curl,CURLOPT_POSTFIELDSIZE,(long)strlen(body)); curl_easy_setopt(curl,CURLOPT_HTTPHEADER,headers); curl_easy_setopt(curl,CURLOPT_WRITEFUNCTION,write_cb); curl_easy_setopt(curl,CURLOPT_WRITEDATA,&response); curl_easy_setopt(curl,CURLOPT_TIMEOUT,20L);
    CURLcode rc=curl_easy_perform(curl); long status=0; curl_easy_getinfo(curl,CURLINFO_RESPONSE_CODE,&status); curl_slist_free_all(headers); curl_easy_cleanup(curl); free(auth); free(body);free(nonce);free(clean_path);free(canonical);free(signature); if(rc!=CURLE_OK)die(curl_easy_strerror(rc));
    cJSON *data=cJSON_Parse(response.data); free(response.data); if(!data)die("Licora returned non-JSON response"); cJSON *success=cJSON_GetObjectItemCaseSensitive(data,"success"); if(!cJSON_IsTrue(success)){cJSON *code=cJSON_GetObjectItemCaseSensitive(data,"code");fprintf(stderr,"Licora error %s (HTTP %ld)\n",cJSON_IsString(code)?code->valuestring:"UNKNOWN",status);cJSON_Delete(data);exit(1);} return data;
}

static cJSON *activate_fixed(LicoraClient *c,const char *license){char context[256];snprintf(context,sizeof(context),"activate:%s",c->app_id);cJSON *o=cJSON_CreateObject();cJSON_AddStringToObject(o,"license_key",license);cJSON_AddStringToObject(o,"app_id",c->app_id);cJSON_AddStringToObject(o,"app_version",c->app_version);cJSON_AddStringToObject(o,"device_id",c->device_id);cJSON_AddStringToObject(o,"device_public_key",c->public_pem);cJSON *r=post_json(c,"activate",o,context,NULL);cJSON_Delete(o);return r;}
static cJSON *status_call(LicoraClient *c,const char *access){char *jti=jwt_jti(access);if(!jti)die("missing access-token jti");cJSON *o=cJSON_CreateObject();cJSON *r=post_json(c,"status",o,jti,access);cJSON_Delete(o);free(jti);return r;}
static cJSON *refresh_call(LicoraClient *c,const char *refresh){char hash[65],context[80];sha256_hex((unsigned char*)refresh,strlen(refresh),hash);snprintf(context,sizeof(context),"refresh:%s",hash);cJSON *o=cJSON_CreateObject();cJSON_AddStringToObject(o,"refresh_token",refresh);cJSON_AddStringToObject(o,"app_version",c->app_version);cJSON *r=post_json(c,"refresh",o,context,NULL);cJSON_Delete(o);return r;}
static cJSON *deactivate_call(LicoraClient *c,const char *access){char *jti=jwt_jti(access);if(!jti)die("missing access-token jti");cJSON *o=cJSON_CreateObject();cJSON *r=post_json(c,"deactivate",o,jti,access);cJSON_Delete(o);free(jti);return r;}

int main(int argc,char **argv){
    if(argc<4){fprintf(stderr,"Usage: licora_v2_client <base-url> <app-id> <license-key> [app-version]\n");return 2;}
    curl_global_init(CURL_GLOBAL_DEFAULT); LicoraClient c={0}; snprintf(c.base,sizeof(c.base),"%s",argv[1]); while(strlen(c.base)&&c.base[strlen(c.base)-1]=='/')c.base[strlen(c.base)-1]='\0'; snprintf(c.app_id,sizeof(c.app_id),"%s",argv[2]); snprintf(c.app_version,sizeof(c.app_version),"%s",argc>4?argv[4]:"1.0.0"); c.device_key=generate_p256(); if(!c.device_key)die("P-256 keygen failed"); c.public_pem=public_pem(c.device_key); unsigned char id[16];RAND_bytes(id,sizeof(id));char *id64=b64url(id,sizeof(id));snprintf(c.device_id,sizeof(c.device_id),"c-%s",id64);free(id64);
    char *access=NULL,*refresh=NULL; cJSON *r=activate_fixed(&c,argv[3]); access=strdup(cJSON_GetObjectItemCaseSensitive(r,"access_token")->valuestring);refresh=strdup(cJSON_GetObjectItemCaseSensitive(r,"refresh_token")->valuestring);cJSON_Delete(r);puts("[PASS] activate");
    r=status_call(&c,access);cJSON_Delete(r);puts("[PASS] status"); r=refresh_call(&c,refresh);free(access);free(refresh);access=strdup(cJSON_GetObjectItemCaseSensitive(r,"access_token")->valuestring);refresh=strdup(cJSON_GetObjectItemCaseSensitive(r,"refresh_token")->valuestring);cJSON_Delete(r);puts("[PASS] refresh (rotated)");
    r=status_call(&c,access);cJSON_Delete(r);puts("[PASS] status-after-refresh"); r=deactivate_call(&c,access);cJSON_Delete(r);puts("[PASS] deactivate");
    free(access);free(refresh);free(c.public_pem);EVP_PKEY_free(c.device_key);curl_global_cleanup();return 0;
}
