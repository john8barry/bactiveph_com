#!/usr/bin/env python3
"""Independent PHP processes exercise durable claims and last-unit reservations."""
from concurrent.futures import ThreadPoolExecutor
import json
import runtime

state=json.loads(runtime.STATE.read_text())
script='/reference/wp-content/plugins/bactive-cashier/tests/concurrency.php'
def invoke(*args):
    return json.loads(runtime.wp(state,['eval-file',script,*args]).splitlines()[-1])
results=[]
for mode in ('same','different','triple'):
    case=invoke('prepare')['case']
    with ThreadPoolExecutor(max_workers=3) as pool:
        futures=[pool.submit(invoke,'create',case,str(actor),mode) for actor in range(3 if mode=='triple' else 2)]
        actors=[f.result() for f in futures]
    after=invoke('inspect',case)
    assert after['physical']==1 and after['held']==1, (mode,actors,after)
    ready=sum(row['state']=='ready' for row in after['rows'])
    if mode=='triple':
        assert sum(a['status']==200 for a in actors)==1, (mode,actors,after)
        assert ready + (1 if actors[2]['status']==200 else 0)==1, (mode,actors,after)
    else:
        assert ready==1, (mode,actors,after)
    if mode=='same':
        assert len(after['rows'])==1, (mode,actors,after)
    results.append({'scenario':mode,'statuses':[a['status'] for a in actors],'ready_cashier_orders':ready,'stock_held':1})
print(json.dumps({'passed':3,'results':results},indent=2))
