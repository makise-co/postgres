echo "PDO"
echo

php select-pdo.php
sudo service postgresql restart
sleep 5
echo

php select-pdo.php
sudo service postgresql restart
sleep 5
echo

php select-pdo.php
sudo service postgresql restart
sleep 5
echo

echo "Swoole (Raw)"
echo

php select-swoole-raw.php
sudo service postgresql restart
sleep 5
echo

php select-swoole-raw.php
sudo service postgresql restart
sleep 5
echo

php select-swoole-raw.php
sudo service postgresql restart
sleep 5
echo

echo "Pq (Raw)"
echo

php select-pq-raw.php
sudo service postgresql restart
sleep 5
echo

php select-pq-raw.php
sudo service postgresql restart
sleep 5
echo

php select-pq-raw.php
sudo service postgresql restart
sleep 5
echo

echo "PgSql (Raw)"
echo

php select-pgsql-raw.php
sudo service postgresql restart
sleep 5
echo

php select-pgsql-raw.php
sudo service postgresql restart
sleep 5
echo

php select-pgsql-raw.php
sudo service postgresql restart
sleep 5
echo

echo "Pq (Buffered)"
echo

php select-pq-buffered.php
sudo service postgresql restart
sleep 5
echo

php select-pq-buffered.php
sudo service postgresql restart
sleep 5
echo

php select-pq-buffered.php
sudo service postgresql restart
sleep 5
echo

echo "Pq (Unbuffered)"
echo

php select-pq-unbuffered.php
sudo service postgresql restart
sleep 5
echo

php select-pq-unbuffered.php
sudo service postgresql restart
sleep 5
echo

php select-pq-unbuffered.php
sudo service postgresql restart
sleep 5
echo

echo "PgSql"
echo

php select-pgsql.php
sudo service postgresql restart
sleep 5
echo

php select-pgsql.php
sudo service postgresql restart
sleep 5
echo

php select-pgsql.php
sudo service postgresql restart
sleep 5
echo

echo "Swoole"
echo

php select-swoole.php
sudo service postgresql restart
sleep 5
echo

php select-swoole.php
sudo service postgresql restart
sleep 5
echo

php select-swoole.php
#sleep 5
echo
