# Sample script to run in Vagrant to get a base image up and running. 

# Run as Root
sudo -i

# Update packages
apt-get update


# Install ZScaler Root CA Cert if Zscaler is installed on your windows dev machine. If not, you will get cert errors when installing composer.
cat << EOF > /usr/local/share/ca-certificates/zscalercert.crt
-----BEGIN CERTIFICATE-----
MIIE0zCCA7ugAwIBAgIJANu+mC2Jt3uTMA0GCSqGSIb3DQEBCwUAMIGhMQswCQYD
VQQGEwJVUzETMBEGA1UECBMKQ2FsaWZvcm5pYTERMA8GA1UEBxMIU2FuIEpvc2Ux
FTATBgNVBAoTDFpzY2FsZXIgSW5jLjEVMBMGA1UECxMMWnNjYWxlciBJbmMuMRgw
FgYDVQQDEw9ac2NhbGVyIFJvb3QgQ0ExIjAgBgkqhkiG9w0BCQEWE3N1cHBvcnRA
enNjYWxlci5jb20wHhcNMTQxMjE5MDAyNzU1WhcNNDIwNTA2MDAyNzU1WjCBoTEL
MAkGA1UEBhMCVVMxEzARBgNVBAgTCkNhbGlmb3JuaWExETAPBgNVBAcTCFNhbiBK
b3NlMRUwEwYDVQQKEwxac2NhbGVyIEluYy4xFTATBgNVBAsTDFpzY2FsZXIgSW5j
LjEYMBYGA1UEAxMPWnNjYWxlciBSb290IENBMSIwIAYJKoZIhvcNAQkBFhNzdXBw
b3J0QHpzY2FsZXIuY29tMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA
qT7STSxZRTgEFFf6doHajSc1vk5jmzmM6BWuOo044EsaTc9eVEV/HjH/1DWzZtcr
fTj+ni205apMTlKBW3UYR+lyLHQ9FoZiDXYXK8poKSV5+Tm0Vls/5Kb8mkhVVqv7
LgYEmvEY7HPY+i1nEGZCa46ZXCOohJ0mBEtB9JVlpDIO+nN0hUMAYYdZ1KZWCMNf
5J/aTZiShsorN2A38iSOhdd+mcRM4iNL3gsLu99XhKnRqKoHeH83lVdfu1XBeoQz
z5V6gA3kbRvhDwoIlTBeMa5l4yRdJAfdpkbFzqiwSgNdhbxTHnYYorDzKfr2rEFM
dsMU0DHdeAZf711+1CunuQIDAQABo4IBCjCCAQYwHQYDVR0OBBYEFLm33UrNww4M
hp1d3+wcBGnFTpjfMIHWBgNVHSMEgc4wgcuAFLm33UrNww4Mhp1d3+wcBGnFTpjf
oYGnpIGkMIGhMQswCQYDVQQGEwJVUzETMBEGA1UECBMKQ2FsaWZvcm5pYTERMA8G
A1UEBxMIU2FuIEpvc2UxFTATBgNVBAoTDFpzY2FsZXIgSW5jLjEVMBMGA1UECxMM
WnNjYWxlciBJbmMuMRgwFgYDVQQDEw9ac2NhbGVyIFJvb3QgQ0ExIjAgBgkqhkiG
9w0BCQEWE3N1cHBvcnRAenNjYWxlci5jb22CCQDbvpgtibd7kzAMBgNVHRMEBTAD
AQH/MA0GCSqGSIb3DQEBCwUAA4IBAQAw0NdJh8w3NsJu4KHuVZUrmZgIohnTm0j+
RTmYQ9IKA/pvxAcA6K1i/LO+Bt+tCX+C0yxqB8qzuo+4vAzoY5JEBhyhBhf1uK+P
/WVWFZN/+hTgpSbZgzUEnWQG2gOVd24msex+0Sr7hyr9vn6OueH+jj+vCMiAm5+u
kd7lLvJsBu3AO3jGWVLyPkS3i6Gf+rwAp1OsRrv3WnbkYcFf9xjuaf4z0hRCrLN2
xFNjavxrHmsH8jPHVvgc1VD0Opja0l/BRVauTrUaoW6tE+wFG5rEcPGS80jjHK4S
pB5iDj2mUZH1T8lzYtuZy0ZPirxmtsk3135+CKNa2OCAhhFjE0xd
-----END CERTIFICATE-----
EOF

update-ca-certificates

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
apt install -y awscli

# Install Subversion
apt install -y subversion

# Install Adminer
apt install -y adminer

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


apt install -y subversion

# Install Redis 
# Install Redis 
apt install -y redis-server

apt install -y redis-tools

sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf

systemctl restart redis.service




