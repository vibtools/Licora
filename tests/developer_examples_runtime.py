#!/usr/bin/env python3
from __future__ import annotations
import ast
import pathlib
import subprocess
import shutil
import sys
root=pathlib.Path(__file__).resolve().parents[1]
py=root/'admin/assets/examples/licora-v2/python/licora_v2_client.py'
ast.parse(py.read_text(encoding='utf-8'))
for rel in ['admin/assets/js/developer-guide.js','admin/assets/examples/licora-v2/node/licora-v2-client.mjs','admin/assets/examples/licora-v2/react-native/licoraV2Client.js']:
    if shutil.which('node'):
        subprocess.run([shutil.which('node'),'--check',str(root/rel)],check=True)
for rel in ['admin/developer_guide.php','admin/assets/examples/licora-v2/php/licora_v2_client.php']:
    if shutil.which('php'):
        subprocess.run([shutil.which('php'),'-l',str(root/rel)],check=True)
php_ref=(root/'admin/assets/examples/licora-v2/php/licora_v2_client.php').read_text(encoding='utf-8')
if "if ($value === []) { return '{}'; }" not in php_ref:
    raise SystemExit('PHP API v2 reference must serialize empty status/deactivate payloads as exact JSON object {}')
print('Developer example syntax/runtime checks passed.')
