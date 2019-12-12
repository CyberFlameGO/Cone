<?php
chdir(realpath(__DIR__));
require "src/Cone.php";
require "src/Package.php";
require "src/UnixPackageManager.php";
use Cone\Cone;
use Cone\Package;
use Cone\UnixPackageManager;
switch(@$argv[1])
{
	case "info":
	case "about":
	case "version":
	case "-version":
	case "--version":
		echo Cone::getString("info_version", [
				"%CONE_VERSION%" => "v".Cone::VERSION,
				"%PHP_VERSION%" => PHP_VERSION
			])."\n".Cone::getString("info_update")."\n";
		break;
	case "ls":
	case "list":
	case "installed":
	case "list-installed":
		Cone::printInstalledPackages();
		echo Cone::getString("list_installable", ["%COMMAND%" => "'cone installable'"])."\n";
		break;
	case "installable":
	case "list-installable":
		echo Cone::getString("installable")."\n";
		foreach(Cone::getPackages() as $package)
		{
			echo $package->getName().": ".$package->getDisplayName();
			if($package->hasVersion())
			{
				echo " ";
				$version = $package->getVersion();
				if(strpos($version, "dev") === false)
				{
					echo "v";
				}
				echo $version;
			}
			echo "\n";
		}
		break;
	case "i":
	case "git":
	case "get":
	case "instal":
	case "install":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("install_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		if(empty($argv[2]))
		{
			die(Cone::getString("syntax").": cone install <".Cone::getString("packages")." ...> [--force]\n");
		}
		if($argv[2] == "gud" || $argv[2] == "good")
		{
			die(Cone::getString("get_good")."\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		$packages = [];
		$force = 0;
		$args = [];
		for($i = 2; $i < count($argv); $i++)
		{
			$args[$i] = strtolower($argv[$i]);
			if($args[$i] == "--force" || $args[$i] == "-f")
			{
				if(++$force == 2)
				{
					echo Cone::getString("double_force")."\n";
				}
				unset($args[$i]);
				continue;
			}
		}
		foreach($args as $name)
		{
			$package = Cone::getPackage($name, true);
			if($package === null)
			{
				die("Unknown package: ".$name."\n");
			}
			if(array_key_exists($package->getName(), $installed_packages))
			{
				$replacements = ["%" => $package->getDisplayName()];
				if($installed_packages[$package->getName()]["manual"])
				{
					echo Cone::getString("already_installed", $replacements)."\n";
				}
				else
				{
					echo Cone::getString("already_installed_dependency", $replacements)."\n";
					$installed_packages[$package->getName()]["manual"] = true;
				}
				continue;
			}
			if(in_array($name, $package->getRiskyAliases()))
			{
				$replacements = [
					"%RISKY_ALIAS%" => $name,
					"%PACKAGE_NAME%" => $package->getDisplayName()
				];
				if($force)
				{
					echo Cone::getString("risky_alias_force", $replacements).".\n";
				}
				else
				{
					echo Cone::getString("risky_alias", $replacements);
					if(!Cone::yesOrNo())
					{
						continue;
					}
				}
			}
			array_push($packages, $package);
		}
		$before = count($installed_packages);
		$env_arr = [];
		foreach($packages as $package)
		{
			try
			{
				$package->install($installed_packages, $force > 0, $env_arr);
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		$count = (count($installed_packages) - $before);
		if($count == 1)
		{
			echo Cone::getString("installed_singular")."\n";
		}
		else
		{
			echo Cone::getString("installed_plural", ["%" => $count])."\n";
		}
		Cone::setInstalledPackages($installed_packages);
		if(count($env_arr) == 1)
		{
			echo Cone::getString("install_env_singular", ["%" => $env_arr[0]])."\n";
		}
		else if(count($env_arr) > 1)
		{
			echo Cone::getString("install_env_plural", ["%" => join(", ", $env_arr)])."\n";
		}
		break;
	case "up":
	case "update":
	case "upgrade":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("update_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		if(@$argv[2] != "--post-install")
		{
			$remote_version = trim(file_get_contents("https://code.getcone.org/version.txt"));
			if(version_compare($remote_version, Cone::VERSION, ">"))
			{
				echo Cone::getString("outdated", ["%" => "v".$remote_version])."\n".Cone::getString("outdated_update")."\n";
				file_put_contents("_update_", "");
				exit;
			}
			echo Cone::getString("up_to_date")."\n".Cone::getString("update_repos")."\n";
		}
		$packages = [];
		$_packages = Cone::getPackages();
		$sources = Cone::getSources();
		$update_sources = false;
		foreach($sources as $url => $name)
		{
			if($is_main_repo = ($url == "https://repository.getcone.org/main.json"))
			{
				$name = Cone::getString("main_repo");
			}
			echo Cone::getString("update_repo", ["%" => $name])." ";
			$res = json_decode(@file_get_contents($url), true);
			if($error = Cone::validateSourceData($res))
			{
				echo $error." ";
				$local = 0;
				foreach($_packages as $package)
				{
					if($package->getSource() == $url)
					{
						array_push($packages, $package->getData());
						$local++;
					}
				}
				if($local == 1)
				{
					echo Cone::getString("repo_restored_singular")."\n";
				}
				else
				{
					echo Cone::getString("repo_restored_plural", ["%" => $local])."\n";
				}
				break;
			}
			foreach($res["packages"] as $package)
			{
				array_push($packages, ["source" => $url] + $package);
			}
			if(count($res["packages"]) == 1)
			{
				echo Cone::getString("repo_packages_singular")."\n";
			}
			else
			{
				echo Cone::getString("repo_packages_plural", ["%" => count($res["packages"])])."\n";
			}
			if(!$is_main_repo)
			{
				if($name != $res["name"])
				{
					echo Cone::getString("repo_rename", [
							"%OLD%" => $name,
							"%NEW%" => $res["name"]
						])."\n";
					$sources[$url] = $res["name"];
					$update_sources = true;
				}
				if($url == "https://packages.getcone.org/main.json")
				{
					unset($sources["https://packages.getcone.org/main.json"]);
					$sources["https://repository.getcone.org/main.json"] = $res["name"];
					$update_sources = true;
				}
			}
		}
		Cone::setPackages($packages);
		if($update_sources)
		{
			Cone::setSources($sources);
		}
		echo Cone::getString("update_packages")."\n";
		$installed_packages = Cone::getInstalledPackagesList();
		foreach($installed_packages as $name => $data)
		{
			try
			{
				$package = Cone::getPackage($name);
				if($package !== null)
				{
					$package->update($installed_packages);
				}
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		echo "Finishing up...\n";
		Cone::removeUnneededDependencies($installed_packages);
		Cone::setInstalledPackages($installed_packages);
		if(@$argv[2] != "--post-install" && $native = UnixPackageManager::getNativePackageManager())
		{
			echo Cone::getString("update_native", ["%" => $native]);
			if(Cone::yesOrNo())
			{
				UnixPackageManager::updateAllPackages();
			}
		}
		break;
	case "force-self-update":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("update_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		echo Cone::getString("advanced_user_prompt");
		if(!Cone::noOrYes())
		{
			die(Cone::getString("abort")."\n");
		}
		file_put_contents("_update_", "");
		break;
	case "rm":
	case "del":
	case "delete":
	case "remove":
	case "uninstal":
	case "uninstall":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("uninstall_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		$packages = [];
		for($i = 2; $i < count($argv); $i++)
		{
			$name = strtolower($argv[$i]);
			if($name == "cone")
			{
				die(Cone::getString("uninstall_cone", ["%COMMAND%" => 'cone self-uninstall'])."\n");
			}
			$p = Cone::getPackage($name, true);
			if($p === null || !array_key_exists($p->getName(), $installed_packages))
			{
				echo Cone::getString("not_installed", ["%" => $name])."\n";
				continue;
			}
			array_push($packages, $p->getName());
		}
		foreach($packages as $package)
		{
			foreach($installed_packages as $name => $data)
			{
				if(in_array($name, $packages))
				{
					continue;
				}
				$p = Cone::getPackage($name);
				if($p != null && in_array($package, $p->getDependenciesList()))
				{
					die(Cone::getString("uninstall_dependency", [
							"%OTHER_PACKAGE%" => $p->getDisplayName(),
							"%UNINSTALL_TARGET%" => $package
						])."\n");
				}
			}
		}
		$before = count($installed_packages);
		foreach($packages as $package)
		{
			echo Cone::getString("uninstall_package", ["%" => $package])."\n";
			try
			{
				(new Package(["name" => $package]))->uninstall($installed_packages);
			}
			catch(Exception $e)
			{
				echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
			}
		}
		Cone::removeUnneededDependencies($installed_packages);
		$count = ($before - count($installed_packages));
		if($count == 1)
		{
			echo Cone::getString("uninstalled_singular")."\n";
		}
		else
		{
			echo Cone::getString("uninstalled_plural", ["%" => $count])."\n";
		}
		Cone::setInstalledPackages($installed_packages);
		break;
	case "repos":
	case "list-repos":
	case "repositories":
	case "list-repositories":
	case "sources":
	case "list-sources":
		$sources = Cone::getSources();
		foreach($sources as $url => $name)
		{
			echo "$name\t$url\n";
		}
		break;
	case "add-repo":
	case "add-repository":
	case "add-source":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("repo_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		if(empty($argv[2]))
		{
			die(Cone::getString("syntax").": cone add-source <url>\n");
		}
		$sources = Cone::getSources();
		foreach($sources as $url => $name)
		{
			if(strtolower($url) == strtolower($argv[2]))
			{
				die(Cone::getString("repo_duplicate", ["%" => $name])."\n");
			}
		}
		echo "Fetching... ";
		$res = @file_get_contents($argv[2]);
		if(!$res)
		{
			die(Cone::getString("repo_http_error")."\n");
		}
		$res = json_decode($res, true);
		if($res === null)
		{
			die(Cone::getString("repo_invalid_json")."\n");
		}
		if($error = Cone::validateSourceData($res))
		{
			die($error."\n");
		}
		$sources[$argv[2]] = $res["name"];
		Cone::setSources($sources);
		echo Cone::getString("repo_add_success", ["%" => $res["name"]])."\n".Cone::getString("repo_update", ["%COMMAND%" => "'cone update'"])."\n";
		break;
	case "rm-repo":
	case "rm-repository":
	case "rm-source":
	case "del-repo":
	case "del-repository":
	case "del-source":
	case "delete-repo":
	case "delete-repository":
	case "delete-source":
	case "remove-repo":
	case "remove-repository":
	case "remove-source":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("repo_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		if(empty($argv[2]))
		{
			die(Cone::getString("syntax").": cone remove-source <url>\n");
		}
		$sources = Cone::getSources();
		if(!array_key_exists($argv[2], $sources))
		{
			die(Cone::getString("repo_unknown", ["%COMMAND%" => "'cone repositories'"]));
		}
		if($argv[2] == "https://repository.getcone.org/main.json")
		{
			echo Cone::getString("advanced_user_prompt");
			if(!Cone::noOrYes())
			{
				die(Cone::getString("abort")."\n");
			}
			Cone::timeToContemplate();
		}
		$name = $sources[$argv[2]];
		unset($sources[$argv[2]]);
		Cone::setSources($sources);
		echo Cone::getString("repo_remove_success", ["%" => $res["name"]])."\n".Cone::getString("repo_update", ["%COMMAND%" => "'cone update'"])."\n";
		echo "Successfully removed {$name}.\nUse 'cone update' once you're finished managing package sources.\n";
		break;
	case "self-uninstall":
		if(!Cone::isAdmin())
		{
			die(Cone::getString("self_uninstall_elevate", ["%ADMIN%" => Cone::rootOrAdmin()])."\n");
		}
		$installed_packages = Cone::getInstalledPackagesList();
		if(count($installed_packages) > 0)
		{
			echo Cone::getString("self_uninstall_packages")." ";
			Cone::printInstalledPackages($installed_packages);
			echo Cone::getString("self_uninstall_packages_prompt");
			if(!Cone::noOrYes())
			{
				die(Cone::getString("abort")."\n");
			}
			Cone::timeToContemplate();
			foreach($installed_packages as $name => $data)
			{
				echo Cone::getString("uninstall_package", ["%" => $name]);
				try
				{
					(new Package(["name" => $name]))->uninstall();
				}
				catch(Exception $e)
				{
					echo $e->getMessage()."\n".$e->getTraceAsString()."\n";
				}
			}
		}
		else
		{
			echo Cone::getString("self_uninstall_prompt");
			if(!Cone::noOrYes())
			{
				die(Cone::getString("abort")."\n");
			}
		}
		echo Cone::getString("self_uninstall_php");
		$remove_php = Cone::yesOrNo();
		if(Cone::isWindows())
		{
			$path = str_replace(realpath("path")."\\;", "", getenv("PATH"));
			if($remove_php)
			{
				$phpdir = realpath(dirname(shell_exec("WHERE php.exe")));
				$path = str_replace($phpdir."\\;", "", $path);
				file_put_contents("_uninstall_php_", $phpdir);
			}
			shell_exec('REG ADD "HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /F /V PATH /T REG_SZ /D "'.$path.'"');
		}
		else if($remove_php)
		{
			UnixPackageManager::removePackage("php-cli");
		}
		file_put_contents("_uninstall_", "");
		break;
	default:
		echo Cone::getString("syntax").": cone <".Cone::getString("command")." [".Cone::getString("arguments")." ...]>\n";
		echo "\n";
		echo Cone::getString("help")."\n";
		echo "\n";
		echo Cone::getString("help_category_info")."\n";
		echo "  info                           ".Cone::getString("help_info")."\n";
		echo "  list                           ".Cone::getString("help_list")."\n";
		echo "  installable                    ".Cone::getString("help_installable")."\n";
		echo "\n";
		echo Cone::getString("help_category_packages")."\n";
		echo "  update                         ".Cone::getString("help_update")."\n";
		echo "  get <".Cone::getString("packages")." ...> [--force]   ".Cone::getString("help_install")."\n";
		echo "  remove <".Cone::getString("packages")." ...>          ".Cone::getString("help_remove")."\n";
		echo "  self-uninstall                 ".Cone::getString("help_self_uninstall")."\n";
		echo "  force-self-update              ".Cone::getString("help_force_self_update")."\n";
		echo "\n";
		echo Cone::getString("help_category_repositories")."\n";
		echo "   repositories                  ".Cone::getString("help_repositories")."\n";
		echo "   add-repository <url>          ".Cone::getString("help_add_repository")."\n";
		echo "   remove-repository <url>       ".Cone::getString("help_remove_repository")."\n";
		echo "\n";
}
