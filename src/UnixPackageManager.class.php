<?php
namespace Cone;
use Exception;
class UnixPackageManager
{
	private static $native_package_manager;

	/**
	 * @param $package
	 * @throws Exception
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
	 * @return mixed|string
	 * @throws Exception
	 */
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

	static function getSupportedPackageManagers()
	{
		return [
			"aptitude",
			"apt-get",
			"pacman"
		];
	}

	/**
	 * @throws Exception
	 */
	static function updateAllPackages()
	{
		switch($mgr = self::getNativePackageManager())
		{
			case "aptitude":
			case "apt-get":
				passthru("{$mgr} -y update && {$mgr} -y upgrade");
				break;
			case "pacman":
				passthru("pacman --noconfirm -Syu");
		}
	}

	/**
	 * @param $package
	 * @throws Exception
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
