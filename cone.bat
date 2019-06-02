@ECHO OFF
IF EXIST "%ProgramFiles%\Hell.sh\Cone\_update_" DEL _update_
php "%ProgramFiles%\Hell.sh\Cone\src\cli.php" %*
IF EXIST "%ProgramFiles%\Hell.sh\Cone\_update_" (
	ECHO Downloading updater...
	powershell -Command "Invoke-WebRequest https://code.getcone.org/install.bat -UseBasicParsing -OutFile '%tmp%\Cone_update.bat'"
	"%tmp%\Cone_update.bat"
)
