# Sample script to run in Vagrant to get a base image up and running. 

# Run as Root
sudo -i

# Update packages
apt-get update

# Install basic required packages, twice to make sure no temporary errors stop us
apt-get install -y nginx php-fpm php-cli php-mbstring php-mysql php-zip php-ldap php-curl php-xml php-soap ntp
apt-get install -y nginx php-fpm php-cli php-mbstring php-mysql php-zip php-ldap php-curl php-xml php-soap ntp

sudo apt install -y php-cli unzip

# Install Composer
curl -sS https://getcomposer.org/installer -o composer-setup.php
HASH=`curl -sS https://composer.github.io/installer.sig`
echo $HASH
php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Install net tools
apt install -y net-tools

echo "Installing NGINX Web Server"
DEBIAN_FRONTEND=noninteractive
# stop nginx
service nginx stop

# Remove any existing nginx config files
rm /etc/nginx/sites-enabled/*

# Put our config files in /opt
mkdir -p /opt/
cd /opt/
git clone https://github.com/nginxrocks/nginx.git

mkdir -p /opt/php-amazon-connect-management/etc
cat << EOF > /opt/php-amazon-connect-management/etc/nginx.conf
server {
    root /opt/php-amazon-connect-management/public;
    server_name acm.local;

    include /opt/nginx/include/listenssl.conf;
    include /opt/nginx/include/tlsclientoption.conf;
    include /opt/nginx/include/security/acao-star.conf;
    include /opt/nginx/include/nocache.conf;
    include /opt/nginx/include/phpfpm.conf;
}
EOF

# Generate a unique diffie-hellman initialization prime & grab our crypto files.
#openssl dhparam -out /etc/ssl/private/dhparams.pem 2048

# Finally link our new global nginx config file to the etc sites enabled directory
ln -s /opt/nginx/nginx.conf /etc/nginx/sites-enabled/nginx.conf


#MODIFY NGINX LIBRARY FOR UBUNTU 20
sed -i 's/php7.2-fpm.sock/php7.4-fpm.sock/' /opt/nginx/include/phpfpm.conf
sed -i 's/php7.2-fpm.sock/php7.4-fpm.sock/' /opt/nginx/server/localhost.conf

# start services
service nginx start

# Install MySQL
apt install -y mysql-server
#apt install -y mysql-client-core-8.0 
apt install -y mysql-client

# Install Git
apt install -y git 

# Install Supervisor to start workers. 
apt install -y supervisor

# Install AWS CLI
apt install awscli

# Install Subversion
apt install subversion

# Create the DATABASE
DBNAME="acm"
echo "Adding mysql database $DBNAME"
query="CREATE DATABASE $DBNAME DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
mysql -e "$query"

# Create the DATABASE USER
DBUSER="acm" # CHANGE ME!
DBPASS="acm" # CHANGE ME!

echo "Adding mysql user $DBUSER"
query="CREATE USER '$DBUSER'@'%' IDENTIFIED BY '$DBPASS'";
mysql -e "$query"
mysql -e "flush privileges"

# Grant DB Access 
echo "Adding permissions for mysql user $DBUSER on database $DBNAME"
query="GRANT ALL PRIVILEGES ON $DBNAME.* TO '$DBUSER'@'%'";
mysql -e "$query"
mysql -e "flush privileges"

service nginx restart

# Run company specific setup script not included in repo if file exists
FILE=/opt/php-amazon-connect-management/etc/after.sh
if test -f "$FILE"; then
    echo "$FILE exists. Installing server specific settings..."
	chmod +rwx $FILE
	sh $FILE
else 
	echo "Install Completed. "
fi


apt install subversion

# Install Redis 
apt install redis-server

sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf

systemctl restart redis.service

