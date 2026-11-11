#!/usr/bin/env python3
"""Run test classes serially; load the suite definitions for legacy shared helpers.
Fake filesystem stores must not overlap across PHP processes.
"""
import argparse, json, os, re, subprocess, time
from pathlib import Path
parser=argparse.ArgumentParser()
parser.add_argument('--all',action='store_true',help='Include every repository PHP test file')
parser.add_argument('--output',default='/tmp/marketplace-growth-validation')
args=parser.parse_args()
root=Path(__file__).resolve().parent.parent
output=Path(args.output).resolve()
if not str(output).startswith('/tmp/') and not str(output).startswith('/private/tmp/'):
    raise SystemExit('Use a temporary output directory; never write validation logs into public storage.')
output.mkdir(parents=True,exist_ok=True)
files=sorted([p for base in (['tests/Unit','tests/Feature'] if args.all else ['tests/Feature/MarketplaceGrowth']) for p in (root/base).rglob('*Test.php')])
results=[]
for index,file in enumerate(files):
    started=time.monotonic()
    log=output/(str(file.relative_to(root)).replace('/','__')+'.log')
    env={**os.environ,'RANDFILE':str(output/'openssl-random-state')}
    try:
        test_class='Tests\\' + str(file.relative_to(root/'tests').with_suffix('')).replace('/', '\\')
        run=subprocess.run(['php','artisan','test','--filter',re.escape(test_class)+'::','--compact','--no-ansi'],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=180)
        body=run.stdout.decode('utf-8',errors='replace')
        body=re.sub(r'\x1b\[[0-9;]*m','',body)
        if 'No tests found' in body:
            raise RuntimeError('No tests matched '+test_class)
        result={'file':str(file.relative_to(root)),'exit':run.returncode,'seconds':round(time.monotonic()-started,2),'summary':re.findall(r'Tests:\s+([^\n]+)',body)[-1:]}
    except subprocess.TimeoutExpired as error:
        body=(error.stdout or b'').decode('utf-8',errors='replace');result={'file':str(file.relative_to(root)),'exit':'timeout','seconds':180,'summary':[]}
    log.write_text(body)
    results.append(result)
    (output/'results.json').write_text(json.dumps(results,indent=2))
    if result['exit']!=0:print(json.dumps(result),flush=True)
    elif (index+1)%20==0:print(f'Checked {index+1}/{len(files)} files',flush=True)
failed=[r for r in results if r['exit']!=0]
print(json.dumps({'files':len(results),'failed':len(failed),'results':str(output/'results.json')}),flush=True)
raise SystemExit(1 if failed else 0)
