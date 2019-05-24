#!/bin/bash
cd /usr/share/cone || exit 1
php src/cli.php "$@"
if [ -f _update_ ]; then
	echo "Downloading updater..."
	wget -qO- https://getcone.org/install.sh | bash
fi
