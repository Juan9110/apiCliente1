<?php
/**
 * end point para la tabla clientes
 * se realiza la conexión con la base
 * y las consultas
 * se devuelven los datos
 */
include "config.php";
include "utils.php";

//realiza la conexion
$dbConn = connect($db);

//comprueba el método de la llamada
$peticion = $_SERVER['REQUEST_METHOD'];

//comprueba la autorización en las cabeceras
$cabeceras = apache_request_headers();

if(comprobarAutorizacion($cabeceras, $dbConn)){
    switch ($peticion) {
        case 'GET':
            peticionGet($dbConn);
            break;
        case 'POST':
            setNewCliente($dbConn, $_POST);
            break;
        case 'DELETE':
            deleteCliente($dbConn, $_GET);
            break;
        case 'PUT':
            modificaCliente($dbConn, $_GET);
            break;
        default:
            header("HTTP/1.1 400 Bad Request");
    }
}

/**
 * comprueba que la petición venga con una clave que exista en la base con el usuario id = 1
 * @param $cabeceras cabeceras de la petición
 * @param $dbConn objeto con la conexión a la base
 * 
 * @return verdadero o falso
 */
function comprobarAutorizacion($cabeceras, $dbConn){
    if(isset($cabeceras['auth'])){
        //comprueba si la key es correcta
        //esta key debería estar guardada en la bd
        $id = 1;
        $sql = $dbConn->prepare("SELECT clave FROM usuario WHERE id=?");
        $sql->bindParam(1, $id);
        $sql->execute();
        $respuesta = $sql->fetch(PDO::FETCH_ASSOC);
        if($respuesta['clave'] == $cabeceras['auth']){
            return true;
        }
    }
    return false;
}

/**
 * Comprueba si en la petición viene un parámetro id
 * @param $dbConn objeto con la conexión a la base
 * @return llama a la función que corresponda
 */
function peticionGet($dbConn){
    if(isset($_GET['id'])){
        getClientById($dbConn, $_GET['id']);
    }else{
        getAllClients($dbConn);
    }
}

/**
 * Busca un cliente por su id
 * @param $dbConn objeto con la conexión a la base
 * @param $id id del cliente a buscar
 * @return respuesta con el resultado de la consulta: datos del cliente o error
 */
function getClientById($dbConn, $id){

    try{
        //prepara la sentencia
        $sql = $dbConn->prepare("SELECT * FROM clientes WHERE id=?");
        //relaciona los parámetros
        $sql->bindParam(1, $id);

        //ejecuta la sentencia preparada
        $sql->execute();
        $respuesta = $sql->fetch(PDO::FETCH_ASSOC);

        if($respuesta){
            //devuelve los datos
            header("HTTP/1.1 200 OK");
            echo json_encode($respuesta);
        }else{
            //no se ha encontrado el cliente
            header("HTTP/1.1 404 Not Found");
            echo json_encode(["404" => "No encontrado"]);
        }

    }catch (PDOException $e) {
        //se produjo un error
        $error = $e->getMessage();
        echo json_encode(["error" => $error]);
    }
    //termina la ejecución del script
    exit();
}


/**
 * Devuelve todos los clientes
 * @param $dbConn objeto con la conexión a la base
 * @return lista con todos los clientes o error
 */
function getAllClients($dbConn){
    try{
        //prepara la sentencia
        $sql = $dbConn->prepare("SELECT * FROM clientes");
        //ejecuta la sentencia
        $sql->execute();

        $sql->setFetchMode(PDO::FETCH_ASSOC);

        $respuesta = $sql->fetchAll();

        if($respuesta){
            //devuelve los datos
            header("HTTP/1.1 200 OK");
            echo json_encode($respuesta);
        }else{
            //no hay datos
            header("HTTP/1.1 404 Not Found");
            echo json_encode(["404" => "No encontrado"]);
        }

    }catch (PDOException $e) {
        //se produjo un error
        $error = $e->getMessage();
        echo json_encode(["error" => $error]);
    }

        exit();
}

/**
 * Inserta nuevo cliente
 * @param $dbConn objeto con la conexión a la base
 * @param $cliente array con los datos del nuevo cliente
 * @return id del cliente insertado o error
 */
function setNewCliente($dbConn, $cliente){
    try{
        //crea la consulta
        $sql = "INSERT INTO clientes (nombre, apellidos, telefono, email, detalle)
                VALUES (?,?,?,?,?)";

        //prepara la sentencia
        $statement = $dbConn->prepare($sql);

        //relaciona los parámetros
        $statement->bindParam(1, $cliente['nombre']);
        $statement->bindParam(2, $cliente['apellidos']);
        $statement->bindParam(3, $cliente['telefono']);
        $statement->bindParam(4, $cliente['email']);
        $statement->bindParam(5, $cliente['detalle']);

        //ejecuta la sentencia
        $statement->execute();

        //rescatamos el id del cliente insertado
        $clienteId = $dbConn->lastInsertId();

        if($clienteId){
            $input['id'] = $clienteId;
            //devuelve los datos
            header("HTTP/1.1 200 OK");
            echo json_encode($input);
        }
    }catch (PDOException $e) {
        //se produjo un error
        $error = $e->getMessage();
        echo json_encode(["error" => $error]);
    }
        exit();
}

/**
 * Elimina un cliente por su id
 * @param $dbConn objeto con la conexión a la base
 * @param $datos con el id
 * @return resultado de la eliminación
 */
function deleteCliente($dbConn, $datos){
    if(isset($datos['id'])){
        $id = $datos['id'];
        try{
            //prepara la consulta
            $statement = $dbConn->prepare("DELETE FROM clientes WHERE id=?");

            //relaciona los parámetros
            $statement->bindParam(1, $id);

            //ejecuta la sentencia
            $statement->execute();

            //comprobamos el número de filas que se han borrado
            $registros = $statement->rowCount();

            header("HTTP/1.1 200 OK");
            echo json_encode(["Registros eliminados" => $registros]);

        }catch (PDOException $e) {
            //se produjo un error
            $error = $e->getMessage();
           echo json_encode(["error" => $error]);
        } 
    }else{
        //falta el id
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["400" => "Solicitud incorrecta"]);
    }

        exit();

}

/**
 * Actualiza un cliente relacionado con un id de la base de datos 
 * @param $dbConn objeto con la conexión a la base
 * @param $datos nuevos datos
 * @return resultado de la modificación
 */
function modificaCliente($dbConn, $datos){
        //comprueba si vienen todos los datos necesarios para la modificación
        if(datosCorrectos($datos)){
            $nombre = $datos['nombre'];         
            $apellidos = $datos['apellidos'];
            $telefono = $datos['telefono'];
            $email = $datos['email'];
            $detalle = $datos['detalle'];
            $id = $datos['id'];
        }else{
            //faltan datos
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(["400" => "Solicitud incorrecta"]);
            exit();
        }

    try{
        //crea la consulta
        $sql = "UPDATE clientes
                SET nombre = ?,
                apellidos = ?,
                telefono = ?,
                email = ?,
                detalle = ?
                WHERE id = ?";
        //prepara la sentencia
        $statement = $dbConn->prepare($sql);

        //relaciona los parámetros
        $statement->bindParam(1, $nombre);
        $statement->bindParam(2, $apellidos);
        $statement->bindParam(3, $telefono);
        $statement->bindParam(4, $email);
        $statement->bindParam(5, $detalle);
        $statement->bindParam(6, $id);

        //ejecuta la consulta
        $statement->execute();

        //comprueba cuantos registros han sido modificados
        $registros = $statement->rowCount();

        header("HTTP/1.1 200 OK");
        echo json_encode(["Registros modificados" => $registros]);

    }catch (PDOException $e) {
        //se produjo un error
        $error = $e->getMessage();
       echo json_encode(["error" => $error]);
    } 
    exit();
}


/**
 * Comprueba que vengan los parámetros y que no vengan vacíos
 * @param array con los datos
 * @return verdadero o falso
 */
function datosCorrectos($datos){
    return isset($datos['nombre']) && 
                 !empty(trim($datos['nombre'])) &&
                 isset($datos['apellidos']) && 
                 !empty(trim($datos['apellidos'])) &&
                 isset($datos['telefono']) && 
                 !empty(trim($datos['telefono'])) &&
                 isset($datos['email']) && 
                 !empty(trim($datos['email'])) &&
                 isset($datos['detalle']) && 
                 !empty(trim($datos['detalle'])) &&
                 isset($datos['id']) && 
                 !empty(trim($datos['id']));
}
© 2021 GitHub, Inc.
Terms
Privacy
Security
Status
Docs
Contact GitHub
Pricing
API
Training
Blog
About
