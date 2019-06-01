#!/bin/bash

CONE_VERSION=0.6.2

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
wget https://github.com/getcone/Cone/archive/v$CONE_VERSION.tar.gz -O Cone.tar.gz

if [ -f _update_ ]; then
	echo "Updating Cone..."
else
	echo "Installing Cone..."
fi
tar -xf Cone.tar.gz
rm -f Cone.tar.gz
if [ -d src ]; then
	rm -rf src
fi
mv Cone-$CONE_VERSION/src/ src
if [ -f packages.json ]; then
	rm -f packages.json
fi
mv Cone-$CONE_VERSION/packages.json packages.json
if [ -f /usr/bin/cone ]; then
	rm /usr/bin/cone
fi
mv Cone-$CONE_VERSION/cone /usr/bin/cone
chmod +x /usr/bin/cone
rm -rf Cone-$CONE_VERSION

if [ -f _update_ ]; then
	rm _update_
	cone update
else
	cone update --post-install
	echo "Cone is now installed."
	cone
fi
