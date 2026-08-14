#!/usr/bin/env python3
"""Build the signed-manifest payload consumed by Licora v5.3.0+ self-updater.

This script does not sign. The release workflow signs the exact JSON bytes with the
repository's dedicated LICORA_UPDATE_SIGNING_PRIVATE_KEY secret.
"""
from __future__ import annotations
import argparse, hashlib, json, re, subprocess, zipfile
from pathlib import Path


def sha256_bytes(data: bytes) -> str: return hashlib.sha256(data).hexdigest()
def sha256_file(path: Path) -> str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''):h.update(chunk)
    return h.hexdigest()

def safe_rel(path: str) -> bool:
    return bool(path) and '\\' not in path and not path.startswith('/') and not re.match(r'^[A-Za-z]:',path) and all(p not in ('','.','..') for p in path.split('/'))

def main() -> int:
    ap=argparse.ArgumentParser();ap.add_argument('--version',required=True);ap.add_argument('--package',required=True);ap.add_argument('--output',required=True);ap.add_argument('--ref',default='HEAD');args=ap.parse_args()
    if not re.fullmatch(r'\d+\.\d+\.\d+',args.version): raise SystemExit('invalid semantic version')
    package=Path(args.package).resolve(); out=Path(args.output).resolve(); prefix=f'Licora-{args.version}/'
    if package.name!=f'Licora-{args.version}.zip': raise SystemExit('package filename/version mismatch')
    with zipfile.ZipFile(package) as z:
        names=z.namelist()
        if not names or any(not n.startswith(prefix) for n in names): raise SystemExit('package archive root mismatch')
        files={}
        for name in names:
            rel=name[len(prefix):]
            if not rel or rel.endswith('/'): continue
            if not safe_rel(rel): raise SystemExit(f'unsafe package path: {rel}')
            files[rel]=sha256_bytes(z.read(name))
        spec_name=prefix+'update/release-spec.json'
        if spec_name not in names: raise SystemExit('update/release-spec.json missing from package')
        spec=json.loads(z.read(spec_name).decode('utf-8'))
    if spec.get('protocol_version')!=1 or spec.get('application')!='Licora' or spec.get('version')!=args.version: raise SystemExit('release spec identity mismatch')
    delete_files=spec.get('delete_files',[])
    if not isinstance(delete_files,list) or any(not isinstance(x,str) or not safe_rel(x) for x in delete_files): raise SystemExit('invalid delete_files')
    migrations=[]
    for item in spec.get('migrations',[]):
        if not isinstance(item,dict): raise SystemExit('invalid migration spec')
        mid=item.get('id'); path=item.get('path'); rollback=item.get('rollback_path')
        if not isinstance(mid,str) or not re.fullmatch(r'[A-Za-z0-9._:-]{3,190}',mid): raise SystemExit('invalid migration id')
        if not isinstance(path,str) or path not in files: raise SystemExit(f'migration file missing from package: {path}')
        destructive=bool(item.get('destructive',False)); idempotent=bool(item.get('idempotent',False))
        if not idempotent and not destructive: raise SystemExit(f'non-destructive migration must be explicitly idempotent: {mid}')
        if destructive and not rollback: raise SystemExit(f'destructive migration requires rollback_path: {mid}')
        m={'id':mid,'path':path,'checksum':files[path],'destructive':destructive,'idempotent':idempotent,'rollback_path':rollback or None}
        if rollback:
            if not isinstance(rollback,str) or rollback not in files: raise SystemExit(f'rollback file missing from package: {rollback}')
            m['rollback_checksum']=files[rollback]
        migrations.append(m)
    commit=subprocess.check_output(['git','rev-parse',f'{args.ref}^{{commit}}'],text=True).strip()
    if not re.fullmatch(r'[0-9a-f]{40}',commit): raise SystemExit('invalid commit identity')
    manifest={
        'protocol_version':1,'application':'Licora','version':args.version,'tag':'v'+args.version,'commit':commit,
        'channel':spec.get('channel','stable'),'minimum_updater':spec.get('minimum_updater','5.3.0'),'minimum_php':spec.get('minimum_php','8.0'),
        'package':{'name':package.name,'sha256':sha256_file(package),'size':package.stat().st_size},
        'migrations':migrations,'delete_files':delete_files,'files':dict(sorted(files.items()))
    }
    out.parent.mkdir(parents=True,exist_ok=True)
    out.write_text(json.dumps(manifest,sort_keys=True,separators=(',',':'),ensure_ascii=False)+'\n',encoding='utf-8',newline='\n')
    print(out);print('files:',len(files));print('sha256:',sha256_file(out));return 0
if __name__=='__main__': raise SystemExit(main())
