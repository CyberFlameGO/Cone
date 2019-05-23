#!/bin/bash

if [ "$(whoami)" != "root" ]; then
	echo "This script needs to be run as root."
	exit 1
fi

if [ "$(which php)" = "" ]; then
	echo "Installing PHP..."
	if [ "$(which aptitude)" != "" ]; then
		aptitude -y install php-cli
	elif [ "$(which apt-get)" != "" ]; then
		apt-get -y install php-cli
	elif [ "$(which pacman)" != "" ]; then
		pacman --noconfirm -S php-cli
	else
		echo "Unable to determine your package manager."
		exit 1
	fi
fi

if [ ! -d /usr/share/cone ]; then
	mkdir /usr/share/cone
fi
cd /usr/share/cone || exit 1

echo "Downloading Cone..."
if [ -f master.tar.gz ]; then
	rm -f master.tar.gz
fi
wget https://github.com/hell-sh/Cone/archive/master.tar.gz

echo "Unpacking Cone..."
tar -xzf master.tar.gz
rm -f master.tar.gz
if [ -d src ]; then
	rm -rf src
fi
mv Cone-master/src/ src
if [ -f packages.json ]; then
	rm -f packages.json
fi
mv Cone-master/packages.json packages.json
rm -rf Cone-master

echo "Registering command..."
cd /usr/bin || exit 1
echo "#!/bin/bash" > cone
echo "php /usr/share/cone/src/cli.php \"\$@\"" >> cone
chmod +x cone

echo "Cone is now installed. Use 'cone help' to get started!"
