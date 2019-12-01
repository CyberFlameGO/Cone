<?php
namespace Cone;
class UnixPackageManager
{
	private static $native_package_manager;

	/**
	 * @param $package
	 */
	static function installPackage($package)
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
				passthru("{$mgr} -y install {$package}");
				break;
			case "pacman":
				passthru("pacman --noconfirm -S {$package}");
		}
	}

	/**
	 * @return string
	 */
	static function getNativePackageManager()
	{
		if(self::$native_package_manager !== null)
		{
			return self::$native_package_manager;
		}
		if(!Cone::isWindows())
		{
			foreach(self::getSupportedPackageManagers() as $mgr)
			{
				if(Cone::which($mgr))
				{
					return (self::$native_package_manager = $mgr);
				}
			}
		}
		return (self::$native_package_manager = "");
	}

	static function getSupportedPackageManagers()
	{
		return [
			"aptitude",
			"apt-get",
			"pacman"
		];
	}

	static function updateAllPackages()
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
				passthru("{$mgr} -y update && {$mgr} -y dist-upgrade");
				break;
			case "pacman":
				passthru("pacman --noconfirm -Syu");
		}
	}

	/**
	 * @param $package
	 */
	static function removePackage($package)
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
				passthru("{$mgr} -y remove {$package}");
				break;
			case "pacman":
				passthru("pacman --noconfirm -Rs {$package}");
		}
	}
}
