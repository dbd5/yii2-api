# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|

  # Use a preconfigured Vagrant box
  config.vm.box = "charlesportwoodii/php7_xenial64"
  config.vm.box_check_update = true

  # Mount the directory with sufficient privileges
  config.vm.synced_folder ".", "/var/www", 
    id: "vagrant-root",
    owner: "vagrant", 
    group: "www-data", 
    mount_options: ["dmode=775,fmode=775"]

  # Provisioning
  config.vm.provision "shell", inline: <<-SHELL, privileged: false

    # Upgrade PHP & Nginx
    echo "Upgrading web server packages"

    # Install Docker CE
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -
    sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"

    # Install Postgresql Dependencies
    sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt/ $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'

    sudo apt-get update
    sudo apt-get remove php7.0-fpm php5.6-fpm disque-server wget -y

    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -

    sudo apt-get install -y php7.1-fpm nginx-mainline apt-transport-https ca-certificates curl docker-ce cowsay libopts25 postgresql-9.6 -y
    sudo ldconfig

    # Generate an ed25519 key if one doesn't exists
    if [[ ! -f /home/vagrant/.ssh/id_ed25519 ]]; then
      cat /dev/zero | ssh-keygen -t ed25519 -f /home/vagrant/.ssh/id_ed25519 -q -N ""
    fi

    if [[ ! -f /etc/php/7.1/conf.d/xdebug.ini ]]
    then
      cd /tmp
      wget http://xdebug.org/files/xdebug-2.5.4.tgz
      tar -xf xdebug-2.5.4.tgz
      cd xdebug-2.5.4
      phpize
      ./configure && sudo make install
      echo "zend_extension=xdebug.so" | sudo tee /etc/php/7.1/conf.d/xdebug.ini
      echo "xdebug.remote_enable = 1" | sudo tee --append  /etc/php/7.1/conf.d/xdebug.ini
      echo "xdebug.remote_autostart = 1" | sudo tee --append /etc/php/7.1/conf.d/xdebug.ini
      echo "xdebug.remote_connect_back = 1" | sudo tee --append /etc/php/7.1/conf.d/xdebug.ini
      echo "xdebug.remote_log = /var/www/logs/xdebug.log" | sudo tee --append /etc/php/7.1/conf.d/xdebug.ini
      sudo systemctl restart php-fpm-7.1
    fi

    # Install libsodium if PHP doens't think it is installed
    if [[ ! -f /etc/php/7.1/conf.d/libsodium.ini ]]
    then
      # Download libsodium proper
      cd /tmp
      wget https://download.libsodium.org/libsodium/releases/libsodium-1.0.12.tar.gz
      tar -xzf libsodium-1.0.12.tar.gz
      cd libsodium-1.0.12
      ./configure && sudo make install

      # Now the PHP extension
      cd /tmp
      git clone https://github.com/jedisct1/libsodium-php -b 1.0.6
      cd libsodium-php
      phpize
      ./configure && sudo make install
      echo "extension=libsodium.so" | sudo tee /etc/php/7.1/conf.d/libsodium.ini
      sudo systemctl restart php-fpm-7.1
    fi

    # Update the user's path for the ~/.bin directory
    export BINDIR="$HOME/.bin"
    if [[ ! -d "${BINDIR}" ]]
    then
      # Add ~/.bin to PATH and create the ~/.bin directory
      echo "export PATH=\"\$PATH:\$HOME/.bin\"" >> /home/vagrant/.bashrc
      mkdir -p /home/vagrant/.bin
      chown -R vagrant:vagrant /home/vagrant/.bin

      # Install Composer
      php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php
      php -r "if (hash('SHA384', file_get_contents('composer-setup.php')) === '7228c001f88bee97506740ef0888240bd8a760b046ee16db8f4095c0d8d525f2367663f22a46b48d072c816e7fe19959') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
      php composer-setup.php --install-dir=/home/vagrant/.bin --filename=composer
      php -r "unlink('composer-setup.php');"

      # Make sure the composer file has the right permissions on it
      chmod a+x /home/vagrant/.bin/composer
      chown -R vagrant:vagrant /home/vagrant/.bin/composer
    fi
    
    # Make composer do Parallel Downloading
    /home/vagrant/.bin/composer global require hirak/prestissimo
    /home/vagrant/.bin/composer global require "fxp/composer-asset-plugin:^1.3.1"

    # Copy the Nginx configuration and restart the web server
    echo "Copying Nginx configuration"
    sudo service nginx stop
    sudo killall nginx

    # Copy the new configuration files in
    if [[ ! -f /etc/nginx/conf/ssl/server.key ]]
    then
      cd /tmp
      openssl ecparam -name prime256v1 -genkey -out server.key
      openssl req -new -x509 -key server.key -out server.crt -days 3650 \
        -subj '/C=US/ST=State/L=City/O=localhost/OU=Development/CN=yii2-api/emailAddress=root@localhost'
      sudo cp /tmp/server.key /etc/nginx/conf/ssl/server.key
      sudo cp /tmp/server.crt /etc/nginx/conf/ssl/server.crt
    fi

    sudo cp /etc/nginx/conf/nginx.conf.default /etc/nginx/conf/nginx.conf
    sudo cp /etc/nginx/conf/fastcgi.conf.default /etc/nginx/conf/fastcgi.conf
    sudo cp /var/www/config/.vagrant/http.conf /etc/nginx/conf/conf.d/http.conf
    sudo service nginx start

    # Create the database
    echo "Creating MySQL database if it is not present"
    mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS root;"

    # Update composer
    /home/vagrant/.bin/composer self-update

    # Install the website
    cd /var/www
    rm -rf /var/www/vendor
    /home/vagrant/.bin/composer install -ovn

    # Pull down the Disque and MailCatcher docker images
    docker pull charlesportwoodii/xenial:disque
    docker pull mailhog/mailhog

    # Start the Disque container
    docker ps | grep disque
    if [[ $? == 1 ]]
    then
      docker run -d -p 7711:7711 --name disque charlesportwoodii/xenial:disque
    fi

    # Start the MailHog container
    docker ps | grep mailhog
    if [[ $? == 1 ]]
    then
      docker run -d -p 1025:1025 -p 8025:8025 --name mailhog mailhog/mailhog
    fi

    if [[ ! -f /var/www/config/config.yml ]]
    then
      cp /var/www/config/config-default.yml /var/www/config/config.yml
    fi;

    echo -e "Your IP is: $(ifconfig enp0s8 | grep "inet addr" | awk '{split($2,a,":"); print a[2] }')\n If this is your first load be sure to configure config/config.yml." | cowsay
  SHELL
end
