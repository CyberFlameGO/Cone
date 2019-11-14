<?php
namespace Cone;
use Exception;
final class Cone
{
	const VERSION = "0.10.1";
	const SOURCES_FILE = __DIR__."/../sources.json";
	const PACKAGES_FILE = __DIR__."/../packages.json";
	const INSTALLED_PACKAGES_FILE = __DIR__."/../installed_packages.json";
	/**
	 * @var $packages_cache string[]
	 */
	private static $sources_cache;
	/**
	 * @var $packages_cache Package[]
	 */
	private static $packages_cache;
	private static $installed_packages_cache;

	static function isMacOS()
	{
		return stristr(PHP_OS, "DAR");
	}

	static function isLinux()
	{
		return stristr(PHP_OS, "LINUX");
	}

	static function isAdmin()
	{
		return self::isWindows() ? trim(shell_exec("NET SESSION 2>NUL")) != "" : trim(shell_exec("whoami")) == "root";
	}

	static function isWindows()
	{
		return defined("PHP_WINDOWS_VERSION_MAJOR");
	}

	static function rootOrAdmin()
	{
		return self::isWindows() ? "administrator" : "root";
	}

	static function getPathFolder()
	{
		return self::isWindows() ? __DIR__."/../path/" : "/usr/bin/";
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

	static function getPhpDir()
	{
		return dirname(self::which("php"));
	}

	static function which($name)
	{
		return trim(shell_exec(self::isWindows() ? "WHERE ".$name." 2>NUL" : "which ".$name));
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
		if(self::isUnix())
		{
			shell_exec("rm -rf ".escapeshellarg($path));
		}
		else if(is_dir($path))
		{
			foreach(scandir($path) as $file)
			{
				if(!in_array($file, [
					".",
					".."
				]))
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

	static function isUnix()
	{
		return !self::isWindows();
	}

	static function download($url, $output)
	{
		shell_exec(self::isWindows() ? "powershell -Command \"[Net.ServicePointManager]::SecurityProtocol = 'tls12, tls11, tls'; Invoke-WebRequest \\\"{$url}\\\" -UseBasicParsing -OutFile \\\"{$output}\\\"\"" : "wget \"{$url}\" -O \"{$output}\"");
	}

	static function extract($file, $target_dir)
	{
		if(self::isWindows())
		{
			$tmp = self::getTmpFolder();
			mkdir("{$tmp}\\Cone_extract");
			shell_exec("COPY \"{$file}\" \"{$tmp}\\Cone_extract.zip\"");
			file_put_contents("tmp.vbs", "Set s = CreateObject(\"Shell.Application\")\r\ns.NameSpace(\"{$tmp}\\Cone_extract\").CopyHere(s.NameSpace(\"{$tmp}\\Cone_extract.zip\").items)");
			shell_exec("cscript //nologo tmp.vbs");
			unlink("tmp.vbs");
			unlink("{$tmp}\\Cone_extract.zip");
			shell_exec("MOVE \"{$tmp}\\Cone_extract\" \"{$target_dir}\"");
		}
		else
		{
			mkdir($target_dir);
			shell_exec("tar -xf \"{$file}\" -C \"{$target_dir}\"");
		}
	}

	static function getTmpFolder()
	{
		return self::isWindows() ? getenv("tmp") : "/tmp/";
	}

	static function getSources()
	{
		if(self::$sources_cache === null)
		{
			if(is_file(self::SOURCES_FILE))
			{
				self::$sources_cache = json_decode(file_get_contents(self::SOURCES_FILE), true);
			}
			else
			{
				self::setSources(["https://packages.getcone.org/main.json" => "Cone Main Repository"]);
			}
		}
		return self::$sources_cache;
	}

	/**
	 * @param array $sources
	 */
	static function setSources($sources)
	{
		self::$sources_cache = $sources;
		file_put_contents(self::SOURCES_FILE, json_encode($sources, JSON_UNESCAPED_SLASHES));
	}

	static function validateSourceData($data)
	{
		if(!is_array($data) || empty($data["name"]) || empty($data["packages"]))
		{
			return "Invalid package source file.";
		}
		if(array_key_exists("cone_min", $data) && version_compare($data["cone_min"], self::VERSION, ">"))
		{
			return "This package list was made for Cone ".$data["cone_min"]." and above.";
		}
		return "";
	}

	/**
	 * @param $packages
	 */
	static function setPackages($packages)
	{
		self::$packages_cache = null;
		file_put_contents(self::PACKAGES_FILE, json_encode($packages, JSON_UNESCAPED_SLASHES));
	}

	static function printInstalledPackages(&$installed_packages = null)
	{
		if($installed_packages === null)
		{
			$installed_packages = Cone::getInstalledPackagesList();
		}
		if(count($installed_packages) == 0)
		{
			echo "0 packages installed.\n";
			return;
		}
		echo count($installed_packages)." package".(count($installed_packages) == 1 ? "" : "s")." installed";
		$packages = [];
		$dependencies = [];
		foreach($installed_packages as $name => $data)
		{
			if($data["manual"])
			{
				$packages[$name] = $data;
			}
			else
			{
				$dependencies[$name] = $data;
			}
		}
		if(empty($dependencies))
		{
			echo ":\n";
		}
		else
		{
			echo "; ".count($packages)." manually-installed:\n";
		}
		foreach($packages as $name => $data)
		{
			echo $data["display_name"];
			if(array_key_exists("version", $data))
			{
				echo " ";
				if(strpos($data["version"], "dev") === false)
				{
					echo "v";
				}
				echo $data["version"];
			}
			echo "\n";
		}
		if(empty($dependencies))
		{
			return;
		}
		echo "and ".count($dependencies)." dependenc".(count($dependencies) == 1 ? "y" : "ies").":\n";
		foreach($dependencies as $name => $data)
		{
			echo $name;
			if(array_key_exists("version", $data))
			{
				echo " v".$data["version"];
			}
			echo "\n";
		}
	}

	static function getInstalledPackagesList(&$installed_packages = null)
	{
		if($installed_packages !== null)
		{
			return $installed_packages;
		}
		if(self::$installed_packages_cache === null)
		{
			self::$installed_packages_cache = is_file(self::INSTALLED_PACKAGES_FILE) ? json_decode(file_get_contents(self::INSTALLED_PACKAGES_FILE), true) : [];
		}
		return self::$installed_packages_cache;
	}

	static function removeUnneededDependencies(&$installed_packages = null)
	{
		$in_flow = $installed_packages !== null;
		if(!$in_flow)
		{
			$installed_packages = Cone::getInstalledPackagesList();
		}
		foreach($installed_packages as $name => $data)
		{
			$package = new Package(["name" => $name]);
			if(!$package->isManuallyInstalled($installed_packages))
			{
				$needed = false;
				foreach($installed_packages as $name_ => $data_)
				{
					if(in_array($name, Cone::getPackage($name_)
										   ->getDependenciesList()))
					{
						$needed = true;
						break;
					}
				}
				if(!$needed)
				{
					echo "Removing unneeded dependency ".$data["display_name"]."...\n";
					try
					{
						$package->uninstall($installed_packages);
					}
					catch(Exception $e)
					{
						echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
					}
				}
			}
		}
		if(!$in_flow)
		{
			Cone::setInstalledPackages($installed_packages);
		}
	}

	static function getPackage($name, $include_aliases = false, $include_risky_aliases = true)
	{
		foreach(self::getPackages() as $package)
		{
			if($package->getName() == $name)
			{
				return $package;
			}
		}
		if($include_aliases)
		{
			foreach(self::$packages_cache as $package)
			{
				if(in_array($name, $package->getAliases()))
				{
					return $package;
				}
			}
			if($include_risky_aliases)
			{
				foreach(self::$packages_cache as $package)
				{
					if(in_array($name, $package->getRiskyAliases()))
					{
						return $package;
					}
				}
			}
		}
		return null;
	}

	/**
	 * @return Package[]
	 */
	static function getPackages()
	{
		if(self::$packages_cache === null)
		{
			self::$packages_cache = [];
			if(is_file(self::PACKAGES_FILE))
			{
				foreach(json_decode(file_get_contents(self::PACKAGES_FILE), true) as $raw_package)
				{
					array_push(self::$packages_cache, new Package($raw_package));
				}
			}
		}
		return self::$packages_cache;
	}

	/**
	 * @param array $installed_packages
	 */
	static function setInstalledPackages($installed_packages)
	{
		self::$installed_packages_cache = $installed_packages;
		file_put_contents(self::INSTALLED_PACKAGES_FILE, json_encode($installed_packages, JSON_UNESCAPED_SLASHES));
	}

	static function yesOrNo()
	{
		echo " [Y/n] ";
		$stdin = fopen("php://stdin", "r");
		$res = substr(fgets($stdin), 0, 1) != "n";
		fclose($stdin);
		return $res;
	}

	static function noOrYes()
	{
		echo " [y/N] ";
		$stdin = fopen("php://stdin", "r");
		$res = substr(fgets($stdin), 0, 1) == "y";
		fclose($stdin);
		return $res;
	}

	static function timeToContemplate()
	{
		echo "3...";
		sleep(1);
		echo " 2...";
		sleep(1);
		echo " 1...";
		sleep(1);
		echo "\n";
	}
}
