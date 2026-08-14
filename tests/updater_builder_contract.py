#!/usr/bin/env python3
from __future__ import annotations
import json, subprocess, tempfile, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/'scripts/build-update-manifest.py'
VERSION='9.9.9'

def run_case(name:str, files:dict[str,bytes], spec:dict, expect_ok:bool, marker:str='') -> None:
    with tempfile.TemporaryDirectory(prefix='licora-builder-test-') as td:
        td=Path(td); package=td/f'Licora-{VERSION}.zip'; out=td/'manifest.json'; prefix=f'Licora-{VERSION}/'
        with zipfile.ZipFile(package,'w',zipfile.ZIP_DEFLATED) as z:
            for rel,data in files.items():z.writestr(prefix+rel,data)
            z.writestr(prefix+'update/release-spec.json',json.dumps(spec).encode())
        gitrepo=td/'git-fixture';gitrepo.mkdir()
        subprocess.run(['git','init','-q'],cwd=gitrepo,check=True)
        subprocess.run(['git','config','user.email','licora-test@example.invalid'],cwd=gitrepo,check=True)
        subprocess.run(['git','config','user.name','Licora Test'],cwd=gitrepo,check=True)
        (gitrepo/'fixture.txt').write_text('fixture\n',encoding='utf-8')
        subprocess.run(['git','add','fixture.txt'],cwd=gitrepo,check=True)
        subprocess.run(['git','commit','-q','-m','fixture'],cwd=gitrepo,check=True)
        proc=subprocess.run(['python3',str(SCRIPT),'--version',VERSION,'--ref','HEAD','--package',str(package),'--output',str(out)],cwd=gitrepo,text=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        if expect_ok and proc.returncode!=0: raise SystemExit(f'FAIL: {name}: expected success, got {proc.stderr.strip()}')
        if not expect_ok and proc.returncode==0: raise SystemExit(f'FAIL: {name}: expected rejection')
        combined=(proc.stdout+'\n'+proc.stderr).lower()
        if marker and marker.lower() not in combined: raise SystemExit(f'FAIL: {name}: expected marker {marker!r}, got {combined.strip()}')

def base_spec()->dict:
    return {'protocol_version':1,'application':'Licora','version':VERSION,'channel':'stable','minimum_updater':'5.3.0','minimum_php':'8.0','upgrade_from':['5.4.0'],'delete_files':[],'migrations':[]}

run_case('valid minimal package',{'README.md':b'ok\n'},base_spec(),True)
run_case('protected package path',{'README.md':b'ok\n','includes/config.local.php':b'secret'},base_spec(),False,'protected package path')
s=base_spec();s['delete_files']=['README.md'];run_case('delete/package overlap',{'README.md':b'ok\n'},s,False,'overlaps packaged source')
s=base_spec();s['migrations']=[{'id':'dup.migration','path':'migration-a.sql','idempotent':True},{'id':'dup.migration','path':'migration-a.sql','idempotent':True}];run_case('duplicate migration id',{'README.md':b'ok\n','migration-a.sql':b'SELECT 1;\n'},s,False,'duplicate migration id')
print('Updater manifest-builder contract checks passed.')
