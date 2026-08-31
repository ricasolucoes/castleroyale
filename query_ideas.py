import sys
import json
try:
    import pymysql
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
            print(f"- {row['nome']}: {row['descricao']} | XP: {row['xp_recompensa']}")
            
        print("\n=== gamificacao_achievements ===")
        cursor.execute("SELECT * FROM gamificacao_achievements")
        for row in cursor.fetchall():
            print(f"- {row['nome']}: {row['descricao']} | Pontos: {row['pontos']}")
except Exception as e:
    print(e)
