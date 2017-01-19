<!DOCTYPE HTML>
<!-- FECHA: 24/08/2016 -->
<html>

<head>
	<title>Prueba de carga de poligono shape</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="description" content="Description" />
	<link rel="stylesheet" href="Stylesheet Location" type="text/css" />
</head>

<body>

	<header>
		<nav>
		</nav>
	</header>

	<h1>Cargar poligono</h1>

	<!-- content goes here -->
  <form enctype="multipart/form-data" action="" method="POST">
  Seleccione el archivo para subir: 
  <input name="uploadedfile" type="file" /><br />
  Elija la proyección
  <select name="proj">
    <option value="32719">UTM ZONA 19</option>
    <option value="32720">UTM ZONA 20</option>
    <option value="32721">UTM ZONA 21</option>
    <option value="4326">GEO WGS84</option> 
  </select>
  <br />
  <input type="submit" value="Cargar archivo" />
  </form>

	<footer>
<?php

	//configuracion de la conexion a la BD PostgreSQL;
	$infoDb = array("host" => "localhost", "port" => 5432, "user" => "your user", "pass" => "your pass", "db" => "your database");
	
	//Esquema de la BD donde se subiran los shapefiles.
	$UPLOAD_SCHEMA = "temp"; 
	
if(@$_POST["proj"]){

		//Creando la carpeta temporal para descomprimir el archivo
		$tempdir=tempnam(sys_get_temp_dir(),'');
		if (file_exists($tempdir)) { unlink($tempdir); }
		mkdir($tempdir);
		if (!is_dir($tempdir)) {
			echo 'error al crear la carpeta temporal';
			exit;
		}	
	
		//Descomprimiendo archivo
		$zip = new ZipArchive();
		$source_file = $_FILES["uploadedfile"]["tmp_name"];
		// open the zip file to extract
		if ($zip->open($source_file) !== true) {
			echo "No se pudo descomprimir el archivo $source_file";
			exit;
		}

		// place in the temp folder
		if ($zip->extractTo($tempdir) !== true) {
			$zip->close();
			echo "No se pudo extraer los archivos a la capeta $tempdir";
			exit;
		}	
		$zip->close();

		//verificar si contiene los archivos basicos para leer shapefile: Shape (.shp),dBase (.dbf)
		$shapefile = glob($tempdir . "/*.shp");
		if(!$shapefile){
			echo "No se encontro archivo .shp";
			exit;
		}
		if(count($shapefile) > 1){ //verificando que el archivo empaquetado no contenga mas de un shape.
			echo "El archivo empaquetado contiene mas de 1 shape.";
			exit;
		}
		$dbffile = substr($shapefile[0], 0, -3).'dbf';
		if (!(is_readable($dbffile) && is_file($dbffile))){
			echo "Falta el archivo .dbf";
			exit;
		}
		
		//leyendo el encabezado del archivo shapefile
		require_once("shpparser/shpparser.php");
		$shp = new shpParser();
		$shp->load($shapefile[0]);
		
		//verificando el sistema de proyeccion
		$bbox = $shp->headerInfo()["boundingBox"];
		$xc = $bbox["xmin"] + ($bbox["xmax"] - $bbox["xmin"]);
		$yc = $bbox["ymin"] + ($bbox["ymax"] - $bbox["ymin"]);
		$bboxProj = array();
		if($_POST["proj"] == 4326){
			$bboxProj = array("xmin" => -180, "ymin" => -90, "xmax" => 180, "ymax" => 90);
		} else {
			$bboxProj = array("xmin" => 166021.4431, "ymin" => 1116915.0440, "xmax" => 833978.5569, "ymax" => 10000000.0000);
		}
		
		if($bboxProj["xmin"] > $xc || $xc > $bboxProj["xmax"] || $bboxProj["ymin"] > $yc || $yc > $bboxProj["ymax"]){
			echo "Las coordenadas no corresponden al sistema de proyección de referencia.";
			exit;
		}	
		
		//Generando un nombre aleatorio para la nueva tabla de PostgreSQL.
		$bytes = openssl_random_pseudo_bytes(4, $cstrong);
		$hex   = str_shuffle("abcdefg").bin2hex($bytes);
		$tablename = "f".date('Ymd').($hex); //el nombre de una tabla en postgresql debe comenzar con carater, la letra f se refiere a 'feature'
	
		//creando el comando para generar el archivo SQL
		$command = "shp2pgsql -s ".$_POST["proj"].":4326 -g the_geom -I -W \"latin1\" ".$shapefile[0]." ".$UPLOAD_SCHEMA.".\"".$tablename."\" > ".$tempdir."/".$tablename.".sql";
	
		//Ejecutando el comando
		//En windows : Previamente se debe adicionar la ruta del archivo de comando shp2pgsql.exe a la variable "Path" del entorno del sistema.
		exec($command,$out,$ret);
		//Ejecutando en la Geodatabase el archivo SQL
		//Se debe tener activa la extension de PHP para conectarse a postgresql

		$db = pg_connect("host=".$infoDb["host"]." port=".$infoDb["port"]." dbname=".$infoDb["db"]." user=".$infoDb["user"]." password=".$infoDb["pass"]);
		$filename = $tempdir."/".$tablename.".sql";
		$handle = fopen($filename, "r");
		$query = fread($handle, filesize($filename)); 
		$result = pg_query($db,$query);
		if (!$result) {  
 			echo "No se pudo crear la tabla $tablename en la BD.";
		}
		
		echo "Se creo la tabla $UPLOAD_SCHEMA.$tablename con exito!!!.";
}

?>
	</footer>

</body>

</html>

