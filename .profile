cp index-wait.html index.html
while [ 1 ]; do
  echo Refreshing...
  php-cgi -f index-main.php refresh $* > tmp
  cp tmp index.html
  echo Done! Sleeping...
  sleep 5
done&
