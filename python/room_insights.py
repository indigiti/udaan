#!/usr/bin/env python3
from __future__ import annotations
import json, sys, pathlib

def main(path: str):
    room=json.loads(pathlib.Path(path).read_text(encoding='utf-8'))
    ps=[p for p in room.get('participants',{}).values() if p.get('verified_at')]
    complete=sum(1 for p in ps if p.get('completed'))
    future={}
    for topic in room.get('signals',{}).get('future_topics',[]): future[topic]=future.get(topic,0)+1
    top=max(future,key=future.get) if future else None
    progress=sum(int(p.get('answered_count',0)) for p in ps)
    max_progress=sum(max(1,int(p.get('journey_total',room.get('journey_length',27)))) for p in ps)
    out={'participants':len(ps),'completed':complete,'completion_pct':round(complete/max(1,len(ps))*100),'avg_progress_pct':round(progress/max(1,max_progress)*100),'top_future_topic':top,'future_topics':future,'summary':f"{complete} of {len(ps)} participants completed; average journey progress is {round(progress/max(1,max_progress)*100)}%."}
    print(json.dumps(out,ensure_ascii=False))
if __name__=='__main__':
    if len(sys.argv)!=2: raise SystemExit('usage: room_insights.py room.json')
    main(sys.argv[1])
