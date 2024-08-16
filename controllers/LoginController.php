<?php

namespace Controllers;

use Classes\Email;
use MVC\Router;
use Model\Usuario;

class LoginController
{

    public static function login(Router $router)
    {
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            //echo 'Desde POST';
            $auth = new Usuario($_POST);

            $alertas = $auth->validarLogin();

            if (empty($alertas)) {
                //echo 'El usuario proporcionó correo y contraseña';
                //Comprobar que exista el usuario
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                if ($usuario) {
                    //Verificar la contraseña 
                    if ($usuario->comprobarContrasenaAndVerificado($auth->password)) {
                        //Autenticar el usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['NOMBRE'] = $usuario->nombre . ' ' . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        //debuguear($_SESSION);

                        //Redireccionamiento

                        if ($usuario->admin == 1) {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('location: /admin');
                        } else {
                            header('location: /cliente');
                        }
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login', [
            'alertas' => $alertas
        ]);
    }

    public static function logout()
    {
        echo 'Desde Logout';
    }

    public static function olvide(Router $router)
    {

        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if (empty($alertas)) {
                $usuario = Usuario::buscarPorCampo('email', $auth->email);

                if ($usuario && $usuario->confirmado === "1") {

                    // debuguear('Si existe y esta confirmado');
                    $usuario->crearToken();
                    $usuario->guardar();

                    $email = new Email(
                        $usuario->email,
                        $usuario->nombre,
                        $usuario->token
                    );

                    $email->enviarInstrucciones();

                    Usuario::setAlerta('exito', 'Revisa tu email');
                } else {
                    Usuario::setAlerta('error', 'El Usuario no existe o no esta confirmado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }

    public static function recuperar(Router $router)
    {
        $alertas = [];

        $token = s($_GET['token']);

        $error = false;

        $usuario = Usuario::buscarPorCampo('token',$token);
        
        if (empty($usuario)) {
            Usuario::setAlerta('error', 'Token no valido');
            $error = true;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            if (empty($alertas)) {
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if ($resultado) {
                    header('Location: /');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);
    }

    public static function crear(Router $router)
    {
        $usuario = new Usuario($_POST);

        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            //revisar que alertas este vacia
            if (empty($alertas)) {

                //verificar que el usuario no este  registrado
                $usuario->existeUsuario();


                $resultado = $usuario->existeUsuario();
                if ($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                    //Hashear el password
                    $usuario->hashPassword();
                    //Generar token unico
                    $usuario->crearToken();
                    //Enviar el email
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarConfirmacion();
                    //Crear el usuario
                    $resultado = $usuario->guardar();


                    if ($resultado) {
                        header('Location:/mensaje');
                    }

                    debuguear($usuario);
                }
            }
        }
        $router->render('auth/crear-cuenta', ['usuario' => $usuario, 'alertas' => $alertas]);

        /* echo 'Desde Crear'; */
    }

    public static function confirmar(Router $router)
    {
        $alertas = [];

        $token = s($_GET['token']);

        //debuguear($token);

        $usuario = Usuario::buscarPorCampo('token', $token);

        if (empty($usuario)) {
            //echo 'Token no válido';
            Usuario::setAlerta('error', 'Token no válido');
        } else {
            //Modificar a usuario confirmado
            //echo 'Token valido, confirmando usuario...';

            $usuario->confirmado = 1;
            $usuario->token = '';

            //debuguear($usuario);

            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente');
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }

    public static function mensaje(Router $router)
    {
        $router->render('auth/mensaje');
    }

    public static function admin()
    {
        echo 'Desde admin';
    }

    public static function cliente()
    {
        echo 'Desde cliente';
    }
}
