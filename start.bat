@ECHO OFF
cd %ProgramFiles%\Hell.sh\Cone
IF EXIST _update_ DEL _update_
php src\cli.php %*
IF EXIST _update_ (
	ECHO Downloading updater...
	powershell -Command "Invoke-WebRequest https://getcone.org/install.bat -UseBasicParsing -OutFile install.bat"
	install.bat
	DEL install.bat
)
