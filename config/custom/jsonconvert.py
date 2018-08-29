#!/usr/bin/python
import json
import sys
for line in sys.stdin:
   try:
       print(json.dumps(json.loads(line, parse_float=str, parse_int=str)))
   except Exception:
       pass
exit(0)
