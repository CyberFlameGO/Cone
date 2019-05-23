<?php
namespace hellsh;
class ConePackage
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

	function performSteps($steps)
	{
		foreach($steps as $step)
		{
			switch($step["type"])
			{
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
					file_put_contents($step["name"], file_get_contents($step["url"]));
					break;

				case "delete":
					if(!file_exists($step["value"]))
					{
						echo "Warning: ".$step["value"]." can't be deleted as it doesn't exist.\n";
						break;
					}
					Cone::reallyDelete($step["value"]);
					break;

				case "keep":
					if(!file_exists($step["value"]))
					{
						echo "Warning: ".$step["value"]." can't be kept as it doesn't exist.\n";
						break;
					}
					$dir = __DIR__."/../packages/";
					if(!is_dir($dir))
					{
						mkdir($dir);
					}
					$dir = $dir.$this->name."/";
					if(!is_dir($dir))
					{
						mkdir($dir);
					}
					rename($step["value"], $dir.$step["value"]);
					break;
			}
		}
	}

	function install(&$installed_packages, $indents = 0)
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
				}
			}
		}
		if($indents > 0)
		{
			echo str_repeat("\t", $indents)."- Installing dependency ".$this->name."...\n";
		}
		else
		{
			echo "Installing ".$this->name."...\n";
		}
		foreach($this->getDependencies() as $dependency)
		{
			$dependency->install($installed_packages, $indents + 1);
		}
		if(array_key_exists("install", $this->data))
		{
			$this->performSteps($this->data["install"]);
		}
		if(array_key_exists("shortcuts", $this->data))
		{
			$working_directory = realpath(__DIR__."/../packages/".$this->name);
			if($working_directory === false)
			{
				echo "Warning: Can't create any shortcuts as no file was kept.\n";
			}
			else
			{
				foreach($this->data["shortcuts"] as $shortcut)
				{
					Cone::createPathShortcut($shortcut["name"], Cone::which($shortcut["target_which"]), $shortcut["target_arguments"], realpath(__DIR__."/../packages/".$this->name));
				}
			}
		}
		$installed_packages[$this->name] = ["manual" => $indents == 0];
	}

	function update()
	{
		if(array_key_exists("update", $this->data))
		{
			$this->performSteps($this->data);
		}
	}

	function uninstall()
	{
		$dir = __DIR__."/../packages/".$this->name;
		if(is_dir($dir))
		{
			Cone::reallyDelete($dir);
		}
		foreach($this->data["shortcuts"] as $shortcut)
		{
			unlink(Cone::getPathFolder().$shortcut["name"].(Cone::isWindows() ? ".lnk" : ""));
		}
		if(array_key_exists("uninstall", $this->data))
		{
			$this->performSteps($this->data["uninstall"]);
		}
	}
}
