#!/usr/bin/env python3
"""Build the protocol-v1 signed-manifest payload consumed by Licora v5.3.0+.

This script does not sign. The Release workflow signs the exact JSON bytes with the
repository's dedicated LICORA_UPDATE_SIGNING_PRIVATE_KEY secret. Builder validation
intentionally mirrors the PHP runtime verifier so CI cannot publish an artifact the
installed updater would reject.
"""
from __future__ import annotations
import argparse, hashlib, json, re, stat, subprocess, zipfile
from pathlib import Path

PROTECTED_EXACT={
    'includes/config.local.php','includes/.licora-encryption.key','includes/.licora-installed',
    'includes/.licora-v2-signing-private.pem','includes/.licora-v2-signing-public.pem',
    'includes/updater/update-signing-private.pem',
}
PROTECTED_PREFIXES=('includes/.licora-updater/','logs/','backups/','exports/','.git/')


def sha256_bytes(data: bytes) -> str: return hashlib.sha256(data).hexdigest()
def sha256_file(path: Path) -> str:
    h=hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''):h.update(chunk)
    return h.hexdigest()

def safe_rel(path: str) -> bool:
    return bool(path) and len(path)<=400 and '\x00' not in path and '\\' not in path and not path.startswith('/') and not re.match(r'^[A-Za-z]:',path) and all(p not in ('','.','..') for p in path.split('/'))

def protected(path: str) -> bool:
    return path in PROTECTED_EXACT or path=='.env' or path.startswith('.env.') or any(path.startswith(p) for p in PROTECTED_PREFIXES)

def critical_control(path: str) -> bool:
    return (
        path in {'includes/config.php', 'admin/updates.php'}
        or path.startswith('includes/updater/')
        or path.startswith('admin/ajax/update-')
        or path.startswith('admin/assets/js/licora-updater')
        or path.startswith('admin/assets/css/licora-updater')
    )

def main() -> int:
    ap=argparse.ArgumentParser();ap.add_argument('--version',required=True);ap.add_argument('--package',required=True);ap.add_argument('--output',required=True);ap.add_argument('--ref',default='HEAD');args=ap.parse_args()
    if not re.fullmatch(r'\d+\.\d+\.\d+',args.version): raise SystemExit('invalid semantic version')
    package=Path(args.package).resolve(); out=Path(args.output).resolve(); prefix=f'Licora-{args.version}/'
    if package.name!=f'Licora-{args.version}.zip': raise SystemExit('package filename/version mismatch')
    with zipfile.ZipFile(package) as z:
        infos=z.infolist()
        if not infos or any(not i.filename.startswith(prefix) for i in infos): raise SystemExit('package archive root mismatch')
        files={}; folded={}
        for info in infos:
            rel=info.filename[len(prefix):]
            if not rel: continue
            is_dir=info.is_dir() or rel.endswith('/')
            path=rel.rstrip('/') if is_dir else rel
            if not safe_rel(path): raise SystemExit(f'unsafe package path: {rel}')
            if protected(path) or protected(rel): raise SystemExit(f'protected package path: {rel}')
            mode=(info.external_attr >> 16) & 0o170000
            if mode==stat.S_IFLNK: raise SystemExit(f'symlink package entry: {rel}')
            if mode not in (0,stat.S_IFREG,stat.S_IFDIR): raise SystemExit(f'unsupported special package entry: {rel}')
            if is_dir:
                if mode not in (0,stat.S_IFDIR): raise SystemExit(f'inconsistent directory package entry: {rel}')
                continue
            if mode==stat.S_IFDIR: raise SystemExit(f'inconsistent file package entry: {rel}')
            fold=path.casefold()
            if path in files or fold in folded: raise SystemExit(f'duplicate/case-colliding package path: {path}')
            folded[fold]=path
            files[path]=sha256_bytes(z.read(info))
        spec_name=prefix+'update/release-spec.json'
        if spec_name not in {i.filename for i in infos}: raise SystemExit('update/release-spec.json missing from package')
        spec=json.loads(z.read(spec_name).decode('utf-8'))
    if spec.get('protocol_version')!=1 or spec.get('application')!='Licora' or spec.get('version')!=args.version: raise SystemExit('release spec identity mismatch')
    if spec.get('channel')!='stable': raise SystemExit('only stable release specs are supported')
    minimum_updater=spec.get('minimum_updater'); minimum_php=spec.get('minimum_php')
    if not isinstance(minimum_updater,str) or not re.fullmatch(r'\d+\.\d+\.\d+',minimum_updater): raise SystemExit('invalid minimum_updater version')
    if not isinstance(minimum_php,str) or not re.fullmatch(r'\d+\.\d+(?:\.\d+)?',minimum_php): raise SystemExit('invalid minimum_php version')
    upgrade_from=spec.get('upgrade_from')
    if not isinstance(upgrade_from,list) or not upgrade_from: raise SystemExit('release spec requires non-empty upgrade_from')
    if any(not isinstance(v,str) or not re.fullmatch(r'\d+\.\d+\.\d+',v) for v in upgrade_from): raise SystemExit('invalid upgrade_from version')
    if len(set(upgrade_from)) != len(upgrade_from): raise SystemExit('duplicate upgrade_from version')

    delete_files=spec.get('delete_files',[])
    if not isinstance(delete_files,list) or any(not isinstance(x,str) or not safe_rel(x) for x in delete_files): raise SystemExit('invalid delete_files')
    delete_folded=set(); file_folded={p.casefold() for p in files}
    for path in delete_files:
        fold=path.casefold()
        if fold in delete_folded: raise SystemExit(f'duplicate/case-colliding delete_files path: {path}')
        delete_folded.add(fold)
        if fold in file_folded: raise SystemExit(f'delete_files overlaps packaged source: {path}')
        if protected(path): raise SystemExit(f'cannot delete protected deployment path: {path}')
        if critical_control(path): raise SystemExit(f'protocol v1 cannot delete updater control file: {path}')

    migration_spec=spec.get('migrations',[])
    if not isinstance(migration_spec,list): raise SystemExit('invalid migrations list')
    migrations=[]; migration_ids=set()
    for item in migration_spec:
        if not isinstance(item,dict): raise SystemExit('invalid migration spec')
        mid=item.get('id'); path=item.get('path'); rollback=item.get('rollback_path')
        if not isinstance(mid,str) or not re.fullmatch(r'[A-Za-z0-9._:-]{3,190}',mid): raise SystemExit('invalid migration id')
        if mid in migration_ids: raise SystemExit(f'duplicate migration id: {mid}')
        migration_ids.add(mid)
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
        'channel':'stable','minimum_updater':minimum_updater,'minimum_php':minimum_php,'upgrade_from':upgrade_from,
        'package':{'name':package.name,'sha256':sha256_file(package),'size':package.stat().st_size},
        'migrations':migrations,'delete_files':delete_files,'files':dict(sorted(files.items()))
    }
    out.parent.mkdir(parents=True,exist_ok=True)
    out.write_text(json.dumps(manifest,sort_keys=True,separators=(',',':'),ensure_ascii=False)+'\n',encoding='utf-8',newline='\n')
    print(out);print('files:',len(files));print('sha256:',sha256_file(out));return 0
if __name__=='__main__': raise SystemExit(main())
