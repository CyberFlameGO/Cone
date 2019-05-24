# ![Cone](https://storage.hell.sh/assets/cone/logo.png)

An intuitive package manager that works everywhere.

## Installation

Follow the steps appropriate to your operating system to install or update Cone.

### Windows

1. Download [install.bat](https://getcone.org/install.bat)
2. Right-click install.bat in the download bar or window
3. Select "Show in folder" or "Open Containing Folder" or similar
4. Right-click install.bat
5. Click "Run as administrator"

### Not Windows

	wget -qO- https://getcone.org/install.sh | sudo bash

Unless `sudo` is not installed:

	su -
	wget -qO- https://getcone.org/install.sh | bash
	exit

## Adding a package

You can simply [open an issue](https://github.com/hell-sh/Cone/issues/new) with a link to the project and other information you consider relevant.

Alternatively, you can have a look at the `packages.json` in `%ProgramFiles%\Hell.sh\Cone` or `/usr/share/cone`, try to add working instructions for the project (and its dependencies, if needed), and open a pull request.
