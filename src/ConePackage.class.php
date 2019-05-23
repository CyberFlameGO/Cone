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

	function install(&$installed_packages, $indents = 0)
	{
		if($this->isInstalled())
		{
			return;
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
		switch($this->data["type"])
		{
			case "php_extension":
			if(!Cone::isWindows() && !Cone::which($this->data["unix"]))
			{
				Cone::installUnixPackage($this->data["unix"]);
			}
			file_put_contents(php_ini_loaded_file(), str_replace("\n;extension=".$this->data["name"], "\nextension=".$this->data["name"], file_get_contents(php_ini_loaded_file())));
			break;

			case "php_script_downloader":
			shell_exec("php -r \"file_put_contents('downloader', file_get_contents('".$this->data["url"]."'));\"");
			echo shell_exec("php downloader");
			unlink("downloader");
			if(!is_dir(__DIR__."/../packages"))
			{
				mkdir(__DIR__."/../packages");
			}
			rename($this->data["output_file"], __DIR__."/../packages/".$this->name.".php");
			Cone::createPathShortcut($this->name, Cone::which("php"), $this->name.".php", realpath(__DIR__."/../packages"));
			break;
		}
		$installed_packages[$this->name] = ["manual" => $indents == 0];
	}

	function update()
	{
		switch($this->data["type"])
		{
			case "php_script_downloader":
			shell_exec($this->data["update"]);
			break;
		}
	}

	function uninstall()
	{
		switch($this->data["type"])
		{
			case "php_extension":
			file_put_contents(php_ini_loaded_file(), str_replace("\nextension=".$this->data["name"], "\n;extension=".$this->data["name"], file_get_contents(php_ini_loaded_file())));
			break;

			case "php_script_downloader":
			unlink(__DIR__."/../packages/".$this->name.".php");
			unlink(Cone::getPathFolder().$this->name.(Cone::isWindows() ? ".lnk" : ""));
			break;
		}
	}
}
