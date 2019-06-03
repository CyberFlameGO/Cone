<?php
namespace Cone;
use Exception;
class Package
{
	private $data;

	/**
	 * @param $data array
	 */
	function __construct($data)
	{
		$this->data = $data;
	}

	function getName()
    {
        return $this->data["name"];
    }

    function getDisplayName(&$installed_packages = null)
    {
        if(array_key_exists("display_name", $this->data))
        {
            return $this->data["display_name"];
        }
        if($this->isInstalled($installed_packages))
        {
            return $this->getInstallData($installed_packages)["display_name"];
        }
        return strtoupper(substr($this->getName(), 0, 1)).substr($this->getName(), 1);
    }

	function getInstallData(&$installed_packages = null)
	{
		return @Cone::getInstalledPackagesList($installed_packages)[$this->getName()];
	}

    function isInstalled(&$installed_packages = null)
    {
        return $this->getInstallData($installed_packages) !== null;
    }

	function isManuallyInstalled(&$installed_packages = null)
	{
		return self::getInstallData($installed_packages)["manual"];
	}

	function getDependenciesList()
	{
		return array_key_exists("dependencies", $this->data) ? $this->data["dependencies"] : [];
	}

	function getDependencies()
	{
		$dependencies = [];
		foreach($this->getDependenciesList() as $name)
		{
			array_push($dependencies, Cone::getPackage($name));
		}
		return $dependencies;
	}

	function getAliases()
	{
		return array_key_exists("aliases", $this->data) ? $this->data["aliases"] : [];
	}

	protected function platformSwitch($step, $callback)
	{
		if(Cone::isWindows())
		{
			if(array_key_exists("windows", $step))
			{
				$callback("windows");
			}
		}
		else
		{
			if(array_key_exists("unix", $step))
			{
				$callback("unix");
			}
			if(Cone::isLinux())
			{
				if(array_key_exists("linux", $step))
				{
					$callback("linux");
				}
			}
			else if(Cone::isMacOS())
			{
				if(array_key_exists("macos", $step))
				{
					$callback("macos");
				}
			}
		}
	}

    /**
     * @param $steps
     * @return array
     * @throws Exception
     */
	function performSteps($steps)
	{
		$inverted_actions = [];
		foreach($steps as $step)
		{
			switch($step["type"])
			{
				case "platform_switch":
					$this->platformSwitch($step, function($platform) use ($step)
					{
						$this->performSteps($step[$platform]);
					});
					break;

				case "platform_download_and_extract":
					$this->platformSwitch($step, function($platform) use ($step)
					{
						$archive_ext = ($platform == "windows" ? ".zip" : ".tar.gz");
						$this->performSteps([
							[
								"type" => "download",
								"target" => $step["target"].$archive_ext
							] + $step[$platform],
							[
								"type" => "extract",
								"file" => $step["target"].$archive_ext,
								"target" => $step["target"]
							],
							[
								"type" => "delete",
								"file" => $step["target"].$archive_ext
							]
						]);
					});
					break;

				case "shell_exec":
					shell_exec($step["value"]);
					break;

				case "enable_php_extension":
					array_push($inverted_actions, ["type" => "disable_php_extension"] + $step);
					file_put_contents(
						php_ini_loaded_file(),
						str_replace(
							[
								"\n;extension=".$step["name"],
								"\n;extension=php_".$step["name"].".dll"
							], [
								"\nextension=".$step["name"],
								"\nextension=php_".$step["name"].".dll"
							],
							file_get_contents(php_ini_loaded_file())
						)
					);
					break;

				case "disable_php_extension":
					array_push($inverted_actions, ["type" => "enable_php_extension"] + $step);
					file_put_contents(
						php_ini_loaded_file(),
						str_replace(
							[
								"\nextension=".$step["name"],
								"\nextension=php_".$step["name"].".dll"
							],
							[
								"\n;extension=".$step["name"],
								"\n;extension=php_".$step["name"].".dll"
							],
							file_get_contents(php_ini_loaded_file())
						)
					);
					break;

				case "install_unix_package":
					if(Cone::isUnix())
					{
						array_push($inverted_actions, ["type" => "remove_unix_package"] + $step);
						UnixPackageManager::installPackage($step["name"]);
					}
					break;

				case "remove_unix_package":
				case "uninstall_unix_package":
					if(Cone::isUnix())
					{
						array_push($inverted_actions, ["type" => "install_unix_package"] + $step);
						UnixPackageManager::removePackage($step["name"]);
					}
					break;

				case "download":
					Cone::download($step["url"], $step["target"]);
					if(array_key_exists("hash", $step))
					{
						foreach($step["hash"] as $algo => $hash)
						{
							if(hash_file($algo, $step["target"]) != $hash)
							{
								unlink($step["target"]);
								throw new Exception($step["target"]." signature mismatch");
							}
						}
					}
					break;

				case "extract":
					if(!is_file($step["file"]))
					{
						throw new Exception($step["file"]." can't be extracted as it doesn't exist");
					}
					Cone::extract($step["file"], $step["target"]);
					break;

				case "delete":
					if(!file_exists($step["file"]))
					{
						throw new Exception($step["file"]." can't be deleted as it doesn't exist.");
					}
					Cone::reallyDelete($step["file"]);
					break;

				case "keep":
					if(!file_exists($step["file"]))
					{
						throw new Exception($step["file"]." can't be kept as it doesn't exist");
					}
					$dir = __DIR__."/../packages/".$this->getName()."/";
					if(!is_dir($dir) && $step["as"] != "")
					{
						mkdir($dir);
					}
					rename($step["file"], $dir.$step["as"]);
					break;

				default:
					throw new Exception("Unknown step type: ".$step["type"]);
			}
		}
		return $inverted_actions;
	}

	/**
	 * @param $installed_packages
	 * @param $env_arr
	 * @param $dependency_of
	 * @throws Exception
	 */
	function install(&$installed_packages = null, &$env_arr = [], $dependency_of = null)
	{
		$in_flow = $installed_packages !== null;
		if(!$in_flow)
		{
			$installed_packages = Cone::getInstalledPackagesList();
		}
		if($this->isInstalled($installed_packages))
		{
			return;
		}
		if(array_key_exists("prerequisites", $this->data))
		{
			foreach($this->data["prerequisites"] as $prerequisite)
			{
				switch($prerequisite["type"])
				{
					case "command_not_found":
						if(Cone::which($prerequisite["value"]) != "")
						{
							echo "Not installing ".$this->getDisplayName()." as ".$prerequisite["value"]." is a registered command.\n";
							return;
						}
						break;

					default:
						throw new Exception("Unknown prerequisite type: ".$prerequisite["type"]);
				}
			}
		}
		echo "Installing ";
		if($dependency_of !== null)
		{
			echo $dependency_of." dependency ";
		}
		$installed_packages[$this->getName()] = [
			"display_name" => $this->getDisplayName($installed_packages),
			"manual" => ($dependency_of === null)
		];
		echo $installed_packages[$this->getName()]["display_name"];
		if(array_key_exists("version", $this->data))
		{
			echo " v".$this->data["version"];
			$installed_packages[$this->getName()]["version"] = $this->data["version"];
		}
		echo "...\n";
		if(array_key_exists("dependencies", $this->data))
		{
			foreach($this->getDependencies() as $dependency)
			{
				$dependency->install($installed_packages, $env_arr, $this->getDisplayName());
			}
			echo "All ".$this->getDisplayName()." dependencies are installed.\n";
		}
		if(!is_dir(__DIR__."/../packages/"))
		{
			mkdir(__DIR__."/../packages/");
		}
		$uninstall_actions = [];
		if(array_key_exists("install", $this->data))
		{
			$uninstall_actions = $this->performSteps($this->data["install"]);
		}
		$dir = realpath(__DIR__."/../packages/".$this->getName());
		if(array_key_exists("shortcuts", $this->data))
		{
			if($dir === false)
			{
				throw new Exception("Can't create any shortcuts as no file was kept");
			}
			foreach($this->data["shortcuts"] as $name => $data)
			{
				if(!array_key_exists("target", $data))
				{
					throw new Exception("Shortcut is missing target");
				}
				$target = $dir."/".$data["target"];
				if(Cone::isWindows() && array_key_exists("target_winext", $data))
				{
					$target .= $data["target_winext"];
				}
				$target = realpath($target);
				if($target)
				{
					$target = "\"{$target}\" ";
				}
				else
				{
					$target = $data["target"]." ";
				}
				if(array_key_exists("target_arguments", $data))
				{
					foreach($data["target_arguments"] as $arg)
					{
						if(array_key_exists("path", $arg))
						{
							$target .= "\"".realpath($dir."/".$arg["path"])."\" ";
						}
						else
						{
							$target .= $arg["value"]." ";
						}
					}
				}
				$path = Cone::getPathFolder().$name;
				if(Cone::isWindows())
				{
					file_put_contents($path.".bat", "@ECHO OFF\n".$target."%*");
				}
				else
				{
					file_put_contents($path, "#!/bin/bash\n{$target}\"\$@\"");
					shell_exec("chmod +x ".$path);
				}
			}
			$installed_packages[$this->getName()]["shortcuts"] = array_keys($this->data["shortcuts"]);
		}
		if(array_key_exists("variables", $this->data))
		{
			foreach($this->data["variables"] as $name => $data)
			{
				if(array_key_exists("path", $data))
				{
					$value = realpath($dir."/".$data["path"]);
				}
				else
				{
					$value = $data["value"];
				}
				if(Cone::isWindows())
				{
					shell_exec("SETX /m {$name} \"{$value}\"");
				}
				else
				{
					file_put_contents("/etc/environment", file_get_contents("/etc/environment")."{$name}={$value}\n");
				}
				putenv("{$name}={$value}");
				array_push($env_arr, $name);
			}
			$installed_packages[$this->getName()]["variables"] = array_keys($this->data["variables"]);
		}
		if(Cone::isWindows() && array_key_exists("file_associations", $this->data))
		{
			if($dir === false)
			{
				throw new Exception("Can't create any file associations as no file was kept");
			}
			foreach($this->data["file_associations"] as $ext => $cmd)
			{
				shell_exec("Ftype {$ext}file={$dir}\\{$cmd}\nAssoc .{$ext}={$ext}file");
			}
			$installed_packages[$this->getName()]["file_associations"] = array_keys($this->data["file_associations"]);
		}
		if(array_key_exists("uninstall", $this->data))
		{
			$uninstall_actions = array_merge($uninstall_actions, $this->data["uninstall"]);
		}
		if($uninstall_actions)
		{
			$installed_packages[$this->getName()]["uninstall"] = $uninstall_actions;
		}
		if(!$in_flow)
		{
			Cone::setInstalledPackagesList($installed_packages);
		}
	}

	/**
	 * @throws Exception
	 */
	function update(&$installed_packages = null)
	{
		$in_flow = $installed_packages !== null;
		if(!$in_flow)
		{
			$installed_packages = Cone::getInstalledPackagesList();
		}
		if(array_key_exists("update", $this->data))
		{
			$this->performSteps($this->data["update"]);
		}
		else if(array_key_exists("version", $this->data) && version_compare($this->data["version"], $this->getInstallData()["version"], ">"))
		{
			echo "Updating ".$this->getDisplayName()."...\n";
			$this->uninstall($installed_packages);
			$this->install($installed_packages);
		}
		if(!$in_flow)
		{
			Cone::removeUnneededDependencies($installed_packages);
			Cone::setInstalledPackagesList($installed_packages);
		}
	}

	/**
	 * @throws Exception
	 */
	function uninstall(&$installed_packages = null)
	{
		$in_flow = $installed_packages !== null;
		if(!$in_flow)
		{
			$installed_packages = Cone::getInstalledPackagesList();
		}
		if(!self::isInstalled($installed_packages))
		{
			return;
		}
		$dir = __DIR__."/../packages/".$this->getName();
		if(is_dir($dir))
		{
			Cone::reallyDelete($dir);
		}
		$data = self::getInstallData();
		if(array_key_exists("shortcuts", $data))
		{
			foreach($data["shortcuts"] as $name)
			{
				unlink(Cone::getPathFolder().$name.(Cone::isWindows() ? ".bat" : ""));
			}
		}
		if(array_key_exists("variables", $data))
		{
			if(Cone::isWindows())
			{
				foreach($data["variables"] as $name)
				{
					shell_exec('REG DELETE "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V '.$name);
				}
			}
			else
			{
				$env = [];
				foreach(file("/etc/environment") as $line)
				{
					if($line = trim($line))
					{
						$arr = explode("=", $line, 2);
						$env[$arr[0]] = $arr[1];
					}
				}
				foreach($data["variables"] as $name)
				{
					unset($env[$name]);
				}
				file_put_contents("/etc/environment", join("\n", $env));
			}
		}
		if(Cone::isWindows() && array_key_exists("file_associations", $data))
		{
			foreach($data["file_associations"] as $ext)
			{
				shell_exec("Ftype {$ext}file=\nAssoc .{$ext}=");
			}
		}
		if(array_key_exists("uninstall", $data))
		{
			$this->performSteps($data["uninstall"]);
		}
		unset($installed_packages[$this->getName()]);
		if(!$in_flow)
		{
			Cone::setInstalledPackagesList($installed_packages);
		}
	}
}
