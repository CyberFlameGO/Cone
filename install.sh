#!/bin/bash

CONE_VERSION=0.2.0

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

echo "Downloading Cone v$CONE_VERSION..."
if [ -f Cone.tar.gz ]; then
	rm -f Cone.tar.gz
fi
wget https://github.com/hell-sh/Cone/archive/v$CONE_VERSION.tar.gz -O Cone.tar.gz

echo "Unpacking Cone..."
tar -xzf Cone.tar.gz
rm -f Cone.tar.gz
if [ -d src ]; then
	rm -rf src
fi
mv Cone-$CONE_VERSION/src/ src
if [ -f packages.json ]; then
	rm -f packages.json
fi
mv Cone-$CONE_VERSION/packages.json packages.json
rm -rf Cone-$CONE_VERSION

echo "Registering command..."
cd /usr/bin || exit 1
echo "#!/bin/bash" > cone
echo "php /usr/share/cone/src/cli.php \"\$@\"" >> cone
chmod +x cone

echo "Cone is now installed. Use 'cone help' to get started!"
