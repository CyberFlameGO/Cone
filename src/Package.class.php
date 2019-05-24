<?php
namespace hellsh\Cone;
class Package
{
	public $name;
	private $data;

	function __construct($name, $data)
	{
		$this->name = $name;
		$this->data = $data;
	}

	function isInstalled()
	{
		return array_key_exists($this->name, Cone::getInstalledPackagesList());
	}

	function isManuallyInstalled()
	{
		return Cone::getInstalledPackagesList()[$this->name]["manual"];
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
			$dependencies[$name] = Cone::getPackage($name);
		}
		return $dependencies;
	}

	function getAliases()
	{
		return array_key_exists("aliases", $this->data) ? $this->data["aliases"] : [];
	}

	function performSteps($steps)
	{
		foreach($steps as $step)
		{
			switch($step["type"])
			{
				case "platform_switch":
					if(Cone::isWindows())
					{
						if(array_key_exists("windows", $step))
						{
							$this->performSteps($step["windows"]);
						}
					}
					else
					{
						if(array_key_exists("unix", $step))
						{
							$this->performSteps($step["unix"]);
						}
						if(Cone::isLinux())
						{
							if(array_key_exists("linux", $step))
							{
								$this->performSteps($step["linux"]);
							}
						}
						else if(Cone::isMacOS())
						{
							if(array_key_exists("macos", $step))
							{
								$this->performSteps($step["macos"]);
							}
						}
					}
					break;

				case "shell_exec":
					shell_exec($step["value"]);
					break;

				case "enable_php_extension":
					file_put_contents(php_ini_loaded_file(), str_replace("\n;extension=".$step["name"], "\nextension=".$step["name"], file_get_contents(php_ini_loaded_file())));
					break;

				case "disable_php_extension":
					file_put_contents(php_ini_loaded_file(), str_replace("\nextension=".$step["name"], "\n;extension=".$step["name"], file_get_contents(php_ini_loaded_file())));
					break;

				case "install_unix_package":
					Cone::installUnixPackage($step["name"]);
					break;

				case "download":
					Cone::download($step["url"], $step["name"]);
					if(array_key_exists("hash", $step))
					{
						foreach($step["hash"] as $algo => $hash)
						{
							if(hash_file($algo, $step["name"]) != $hash)
							{
								echo $step["name"]." signature mismatch, aborting install.\n";
								unlink($step["name"]);
								return;
							}
						}
					}
					break;

				case "extract":
					if(!is_file($step["file"]))
					{
						echo "Warning: ".$step["file"]." can't be extracted as it doesn't exist.\n";
						break;
					}
					Cone::extract($step["file"], $step["target"]);
					break;

				case "delete":
					if(!file_exists($step["file"]))
					{
						echo "Warning: ".$step["file"]." can't be deleted as it doesn't exist.\n";
						break;
					}
					Cone::reallyDelete($step["file"]);
					break;

				case "keep":
					if(!file_exists($step["file"]))
					{
						echo "Warning: ".$step["file"]." can't be kept as it doesn't exist.\n";
						break;
					}
					$dir = __DIR__."/../packages/".$this->name."/";
					if(!is_dir($dir) && $step["as"] != "")
					{
						mkdir($dir);
					}
					rename($step["file"], $dir.$step["as"]);
					break;

				default:
					echo "Error: Unknown step type: ".$step["type"]."\n";
			}
		}
	}

	function install(&$installed_packages, $dependency_of = null)
	{
		if($this->isInstalled())
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
							echo "Not installing ".$this->name." as ".$prerequisite["value"]." is a registered command.\n";
							return;
						}
						break;

					default:
						echo "Error: Unknown prerequisite type: ".$prerequisite["type"]."\n";
				}
			}
		}
		if($dependency_of === null)
		{
			echo "Installing ".$this->name."...\n";
		}
		else
		{
			echo "Installing ".$dependency_of." dependency ".$this->name."...\n";
		}
		if(array_key_exists("dependencies", $this->data))
		{
			foreach($this->getDependencies() as $dependency)
			{
				$dependency->install($installed_packages, $this->name);
			}
			echo "All ".$this->name." dependencies are installed.\n";
		}
		if(!is_dir(__DIR__."/../packages/"))
		{
			mkdir(__DIR__."/../packages/");
		}
		if(array_key_exists("install", $this->data))
		{
			$this->performSteps($this->data["install"]);
		}
		$working_directory = realpath(__DIR__."/../packages/".$this->name);
		if(array_key_exists("shortcuts", $this->data))
		{
			if($working_directory === false)
			{
				echo "Warning: Can't create any shortcuts as no file was kept.\n";
			}
			else
			{
				foreach($this->data["shortcuts"] as $shortcut)
				{
					$options = [
						"working_directory" => $working_directory
					];
					if(array_key_exists("target_arguments", $shortcut))
					{
						$options["target_arguments"] = $shortcut["target_arguments"];
					}
					if(array_key_exists("target", $shortcut))
					{
						$options["target"] = realpath($working_directory."/".$shortcut["target"].(Cone::isWindows() ? ".exe" : ""));
					}
					else if(array_key_exists("target_which", $shortcut))
					{
						$options["target"] = Cone::which($shortcut["target_which"]);
					}
					else
					{
						echo "Error: Shortcut is missing target or target_which.\n";
					}
					Cone::createPathShortcut($shortcut["name"], $options);
				}
			}
		}
		if(Cone::isWindows() && array_key_exists("file_associations", $this->data))
		{
			if($working_directory === false)
			{
				echo "Warning: Can't create any file associations as no file was kept.\n";
			}
			else
			{
				foreach($this->data["file_associations"] as $ext => $cmd)
				{
					shell_exec("Assoc .{$ext}={$ext}file\nFtype {$ext}file={$working_directory}\\{$cmd}");
				}
			}
		}
		$installed_packages[$this->name] = ["manual" => ($dependency_of === null)];
		if(array_key_exists("version", $this->data))
		{
			$installed_packages[$this->name]["version"] = $this->data["version"];
		}
	}

	function update()
	{
		if(array_key_exists("update", $this->data))
		{
			$this->performSteps($this->data["update"]);
		}
	}

	function uninstall()
	{
		$dir = __DIR__."/../packages/".$this->name;
		if(is_dir($dir))
		{
			Cone::reallyDelete($dir);
		}
		if(array_key_exists("shortcuts", $this->data))
		{
			foreach($this->data["shortcuts"] as $shortcut)
			{
				unlink(Cone::getPathFolder().$shortcut["name"].(Cone::isWindows() ? ".lnk" : ""));
			}
		}
		if(Cone::isWindows() && array_key_exists("file_associations", $this->data))
		{
			foreach($this->data["file_associations"] as $ext => $cmd)
			{
				shell_exec("Ftype {$ext}file=\nAssoc .{$ext}=");
			}
		}
		if(array_key_exists("uninstall", $this->data))
		{
			$this->performSteps($this->data["uninstall"]);
		}
	}
}
