#!/bin/bash

if [ $(whoami) != "root" ]; then
	echo "This script needs to be run as root."
	exit 1
fi

if [ $(which php) = "" ]; then
	echo "Installing PHP..."
	if [ $(which aptitude) != "" ]; then
		aptitude -y install php-cli
	elif [ $(which apt-get) != "" ]; then
		apt-get -y install php-cli
	elif [ $(which pacman) != "" ]; then
		pacman --noconfirm -S php-cli
	else
		echo "Unable to determine your package manager."
		echo "Please install PHP-CLI manually and then try again."
		exit 1
	fi
fi

if [ -d /usr/share/cone ]; then
	rm -rf /usr/share/cone
fi
mkdir /usr/share/cone
cd /usr/share/cone

echo "Downloading Cone..."
wget https://github.com/hell-sh/Cone/archive/master.tar.gz

echo "Unpacking Cone..."
tar -xzf master.tar.gz
rm -f master.tar.gz
mv Cone-master/src/ src
rm -rf Cone-master

echo "Registering command..."
cd /usr/bin
echo "#!/bin/bash" > cone
echo "" >> cone
echo "php /usr/share/cone/src/cli.php \"\$@\"" >> cone
chmod +x cone

echo "Cone is now installed. Use 'cone help' to get started!"
