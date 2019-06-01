<?php
namespace hellsh\Cone;
use Exception;
class UnixPackageManager
{
	private static $native_package_manager;

	static function getSupportedPackageManagers()
	{
		return ["aptitude", "apt-get", "pacman"];
	}

	static function getNativePackageManager()
	{
		if(Cone::isWindows())
		{
			return "";
		}
		if(self::$native_package_manager !== null)
		{
			return self::$native_package_manager;
		}
		foreach(self::getSupportedPackageManagers() as $mgr)
		{
			if(Cone::which($mgr))
			{
				return (self::$native_package_manager = $mgr);
			}
		}
		throw new Exception("Unable to find native package manager");
	}

	static function installPackage($package)
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
			echo shell_exec("{$mgr} -y install {$package}");
			break;

			case "pacman":
			echo shell_exec("pacman --noconfirm -S {$package}");
		}
	}

	static function updateAllPackages()
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
			echo shell_exec("{$mgr} update && {$mgr} upgrade");
			break;

			case "pacman":
			echo shell_exec("pacman --noconfirm -Syu");
		}
	}

	static function removePackage($package)
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
			echo shell_exec("{$mgr} remove {$package}");
			break;

			case "pacman":
			echo shell_exec("pacman --noconfirm -Rs {$package}");
		}
	}
}
