import sys
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
        cursor.execute("SHOW TABLES LIKE '%gamificacao%'")
        tables = cursor.fetchall()
        print("Tables:", tables)
        for t in tables:
            t_name = list(t.values())[0]
            print(f"\n--- Table {t_name} ---")
            cursor.execute(f"SELECT * FROM {t_name} LIMIT 20")
            rows = cursor.fetchall()
            for r in rows:
                print(r)
except Exception as e:
    print(e)
