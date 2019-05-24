<?php
namespace hellsh;
final class Cone
{
	const VERSION = "0.2.1";
	const PACKAGES_MAJOR = 1;
	const PACKAGES_FILE = __DIR__."/../packages.json";
	const INSTALLED_PACKAGES_FILE = __DIR__."/../installed_packages.json";
	private static $packages_json_cache;
	private static $packages_cache;
	private static $installed_packages_list_cache;

	static function isWindows()
	{
		return defined("PHP_WINDOWS_VERSION_MAJOR");
	}

	static function isAdmin()
	{
		return self::isWindows() ? trim(shell_exec("NET SESSION 2>NUL")) != "" : trim(shell_exec("whoami")) == "root";
	}

	static function installUnixPackage($name)
	{
		if(self::isWindows())
		{
			return;
		}
		echo shell_exec("if [ \"\$(which aptitude)\" != \"\" ]; then\naptitude -y install $name\nelif [ \"\$(which apt-get)\" != \"\" ]; then\napt-get -y install $name\nelif [ \"\$(which pacman)\" != \"\" ]; then\npacman --noconfirm -S $name\nfi");
	}

	static function updateUnixPackages()
	{
		if(self::isWindows())
		{
			return;
		}
		echo shell_exec("if [ \"\$(which aptitude)\" != \"\" ]; then\naptitude update\naptitude -y upgrade\nelif [ \"\$(which apt-get)\" != \"\" ]; then\napt-get update\napt-get -y upgrade\nelif [ \"\$(which pacman)\" != \"\" ]; then\npacman --noconfirm -Syu\nfi");
	}

	static function getPathFolder()
	{
		return self::isWindows() ? __DIR__."/../path/" : "/usr/bin/";
	}

	static function which($name)
	{
		return trim(shell_exec(self::isWindows() ? "WHERE ".$name." 2>NUL" : "which ".$name));
	}

	static function getPhpDir()
	{
		return dirname(self::which("php"));
	}

	static function getPhpIni()
	{
		$ini = php_ini_loaded_file();
		if($ini !== false)
		{
			return $ini;
		}
		$dir = self::getPhpDir();
		copy($dir."/php.ini-development", $dir."/php.ini");
		return $dir."/php.ini";
	}

	static function createPathShortcut($name, $target, $target_arguments, $working_directory)
	{
		$path = self::getPathFolder().$name;
		if(self::isWindows())
		{
			$path .= ".lnk";
			file_put_contents($path, "");
			$path = realpath($path);
			unlink($path);
			file_put_contents("tmp.vbs", "Set oWS = WScript.CreateObject(\"WScript.Shell\")\nSet oLink = oWS.CreateShortcut(\"$path\")\noLink.TargetPath = \"$target\"\noLink.Arguments = \"$target_arguments\"\noLink.WorkingDirectory = \"$working_directory\"\noLink.Save");
			shell_exec("cscript /nologo tmp.vbs");
			unlink("tmp.vbs");
		}
		else
		{
			file_put_contents($path, "#!/bin/bash\ncd $working_directory\n$target $target_arguments \"\$@\"");
			shell_exec("chmod +x ".$path);
		}
	}

	static function reallyDelete($path)
	{
		if(substr($path, -1) == "/")
		{
			$path = substr($path, 0, -1);
		}
		if(!file_exists($path))
		{
			return;
		}
		if(is_dir($path))
		{
			foreach(scandir($path) as $file)
			{
				if(!in_array($file, [".", ".."]))
				{
					self::reallyDelete($path."/".$file);
				}
			}
			rmdir($path);
		}
		else
		{
			unlink($path);
		}
	}

	static function getPackagesJson()
	{
		if(self::$packages_json_cache === NULL)
		{
			self::$packages_json_cache = json_decode(file_get_contents(self::PACKAGES_FILE), true);
		}
		return self::$packages_json_cache;
	}

	static function getPackagesVersion()
	{
		return [
			"major" => self::getPackagesJson()["major"],
			"revision" => self::getPackagesJson()["revision"]
		];
	}

	static function getPackages()
	{
		if(self::$packages_cache === NULL)
		{
			self::$packages_cache = [];
			foreach(self::getPackagesJson()["packages"] as $name => $data)
			{
				self::$packages_cache[$name] = new ConePackage($name, $data);
			}
		}
		return self::$packages_cache;
	}

	static function getPackage($name, $try_aliases = false)
	{
		$package = @self::getPackages()[$name];
		if($try_aliases && $package === null)
		{
			foreach(self::$packages_cache as $p)
			{
				if(in_array($name, $p->getAliases()))
				{
					return $p;
				}
			}
		}
		return $package;
	}

	static function getInstalledPackagesList()
	{
		if(self::$installed_packages_list_cache === NULL)
		{
			self::$installed_packages_list_cache = is_file(self::INSTALLED_PACKAGES_FILE) ? json_decode(file_get_contents(self::INSTALLED_PACKAGES_FILE), true) : [];
		}
		return self::$installed_packages_list_cache;
	}

	static function setInstalledPackagesList($list)
	{
		self::$installed_packages_list_cache = $list;
		file_put_contents(self::INSTALLED_PACKAGES_FILE, json_encode($list));
	}
}
