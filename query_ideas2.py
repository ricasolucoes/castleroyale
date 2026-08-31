import sys
import json
import pymysql
from datetime import date, datetime

def default_serializer(obj):
    if isinstance(obj, (date, datetime)):
        return obj.isoformat()
    raise TypeError(f"Type {type(obj)} not serializable")

try:
    connection = pymysql.connect(
        host='sandbox-banlek-com-v3.cluster-cfdgmgq8rw9y.us-east-1.rds.amazonaws.com',
        user='naoadmin',
        password='b4nL3kpxssS.ndb0x',
        database='sandbox-banlek-com',
        cursorclass=pymysql.cursors.DictCursor
    )
    with connection.cursor() as cursor:
        print("=== gamificacao_missoes_pool ===")
        cursor.execute("SELECT * FROM gamificacao_missoes_pool")
        for row in cursor.fetchall():
            print(json.dumps(row, default=default_serializer, indent=2))
            
        print("\n=== gamificacao_achievements ===")
        cursor.execute("SELECT * FROM gamificacao_achievements")
        for row in cursor.fetchall():
            print(json.dumps(row, default=default_serializer, indent=2))
except Exception as e:
    print(e)
