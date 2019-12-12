<?php
if(empty($argv[1]))
{
	die("Syntax: php bump.php <new_version>");
}
$o = trim(file_get_contents("version.txt"));
$n = $argv[1];
if(version_compare($o, $n, ">"))
{
	echo "Warning: New version is lower than old version.\nPress any key to continue...\n";
	$stdin = fopen("php://stdin", "r");
	fgets($stdin);
	fclose($stdin);
}
file_put_contents("version.txt", $n);
foreach(["install.bat", "install.sh"] as $installer)
{
	file_put_contents($installer, str_replace("CONE_VERSION={$o}", "CONE_VERSION={$n}", file_get_contents($installer)));
}
file_put_contents("src/Cone.php", str_replace("const VERSION = \"$o\";", "const VERSION = \"$n\";", file_get_contents("src/Cone.php")));
