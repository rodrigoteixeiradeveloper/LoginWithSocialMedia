<?php

namespace Source\Controllers;

use Source\Models\User;
use Source\Support\Email;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;

class Auth extends Controller {
    public function __construct($router) {
        parent::__construct($router);
    }

    public function login($data): void {
        $email = filter_var($data["email"], FILTER_VALIDATE_EMAIL);
        $passwd = filter_var($data["passwd"], FILTER_DEFAULT);

        if(!$email || !$passwd) {
            echo $this->ajaxResponse("message", [
                "type" => "alert",
                "message" => "Informe seu e-mail e senha para logar"
            ]);
            return;
        }

        $user = (new User())->find("email = :email", "email={$email}")->fetch();
        if(!$user || !password_verify($passwd, $user->passwd)){
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => "E-mail o senha inválido"
            ]);
            return;
        }

        /** SOCIAL VALIDATE */
        $this->socialValidate($user);

        $_SESSION["user"] = $user->id;
        
        echo $this->ajaxResponse("redirect", [
            "url" => $this->router->route("app.home")
        ]); 

    }

    public function register($data): void {
        $data = filter_var_array($data, FILTER_SANITIZE_STRIPPED);
        if(in_array("", $data)) {
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => "Preencha todos os campos para cadastrar-se"
            ]);
            return;
        }

        $user = new User();
        $user->first_name = $data["first_name"];
        $user->last_name = $data["last_name"];
        $user->email = $data["email"];
        $user->passwd = $data["passwd"];

        /** SOCIAL VALIDATE */
        $this->socialValidate($user);

        if(!$user->save()) {
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => $user->fail()->getMessage()
            ]);
            return;                                
        }

        $_SESSION["user"] = $user->id;

        echo $this->ajaxResponse("redirect", [
            "url" => $this->router->route("app.home")
        ]); 

    }

    public function forget($data): void {
        $email = filter_var($data["email"], FILTER_VALIDATE_EMAIL);
        if(!$email){
            echo $this->ajaxResponse("message", [
                "type" => "alert",
                "message" => "Informe um email válido"
            ]);
            return; 
        }

        $user = (new User())->find("email = :email", "email={$email}")->fetch();
        if(!$user){
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => "E-mail informado não é cadastado"
            ]);
            return;
        }

        $user->forget = (md5(uniqid(rand(), true)));
        $user->save();

        $_SESSION["forget"] = $user->id;

        $email = new Email();
        $email->add(
            "Recupere sua senha | " . site("name"),
            $this->view->render("emails/recover", [
                "user" => $user,
                "link" => $this->router->route("web.reset", [
                    "email" => $user->email,
                    "forget" => $user->forget
                ])
            ]),
            "{$user->first_name} {$user->last_name}",
            $user->email
        )->send();

        if(!$email){
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => $email->error()->getMessage()
            ]);
            return; 
        }
        
        flash("success", "Link de recuperação enviado com sucesso!");

        echo $this->ajaxResponse("redirect", [
            "url" => $this->router->route("web.forget")
        ]);
    }

    public function reset($data): void {
        if(empty($_SESSION["forget"]) || !$user = (new User())->findById($_SESSION["forget"])) {
            flash("error", "Não foi possivel recupera! Tente novamente");
            echo $this->ajaxResponse("redirect", [
                "url" => $this->router->route("web.forget")
            ]);
            return;
        }

        if(empty($data["password"]) || empty($data["password_re"])) {
            echo $this->ajaxResponse("message", [
                "type" => "alert",
                "message" => "Informe e confirme sua nova senha"
            ]);
            return; 
        }

        if($data["password"] != $data["password_re"]) {
            echo $this->ajaxResponse("message", [
                "type" => "alert",
                "message" => "As senhas não coincidem"
            ]);
            return; 
        }

        $user->passwd = $data["password"];
        $user->forget = null;

        if(!$user->save()) {
            echo $this->ajaxResponse("message", [
                "type" => "error",
                "message" => $user->fail()->getMessage()
            ]);
            return;   
        }

        unset($_SERVER["forget"]);

        flash("success", "Senha atualizada com sucesso!");

        echo $this->ajaxResponse("redirect", [
            "url" => $this->router->route("web.login")
        ]);

    }

    public function facebook(): void {
        $facebook = new Facebook(FACEBOOK_LOGIN);
        $error = filter_input(INPUT_GET, "error", FILTER_SANITIZE_STRIPPED);
        $code = filter_input(INPUT_GET, "code", FILTER_SANITIZE_STRIPPED);

        if(!$error && !$code) {
            $auth_url = $facebook->getAuthorizationUrl(["scope" => "email"]);
            header("Location: {$auth_url}");
            return;
        }

        if($error){
            flash("error", "Não foi possivel logar com o Facebook");
            $this->router->redirect("web.login");
        }

        if($code && empty($_SESSION["facebook_auth"])) {
            try {
                $token = $facebook->getAccessToken("authorization_code", ["code" => $code]);
                $_SESSION["facebook_auth"] = serialize($facebook->getResourceOwner($token));
            } catch(Exception $exception){
                flash("error", "Não foi possivel logar com o Facebook");
                $this->router->redirect("web.login");
            }
        }

        /** @var $facebook_user FacebookUser */
        $facebook_user = unserialize($_SESSION["facebook_auth"]);
        $user_by_id = (new User())->find("facebook_id = :facebook_id", "facebook_id={$facebook_user->getId()}")->fetch();

        //Login by Id
        if($user_by_id) {
            unset($_SESSION["facebook_auth"]);

            $_SESSION["user"] = $user_by_id->id;
            $this->router->redirect("app.home");
        }

        //Login by e-mail
        $user_by_email = (new User())->find("email = :email", "email={$facebook_user->getEmail()}")->fetch();

        if($user_by_email) {
            flash("info", "Olá {$facebook_user->getFirstName()}, faça login para conectar seu Facebook");
            $this->router->redirect("web.login");
        }

        //Register if not
        $link = $this->router->route("web.login");
        flash("info", "Olá {$facebook_user->getFirstName()}, se já tem uma conta clique em <a href='{$link}'> FAZER LOGIN</a>, ou complete seu cadastro");
        $this->router->redirect("web.register");

    }

    public function google(): void {
        $google = new Google(GOOGLE_LOGIN);
        $error = filter_input(INPUT_GET, "error", FILTER_SANITIZE_STRIPPED);
        $code = filter_input(INPUT_GET, "code", FILTER_SANITIZE_STRIPPED);

        if(!$error && !$code) {
            $auth_url = $google->getAuthorizationUrl();
            header("Location: {$auth_url}");
            return;
        }

        if($error){
            flash("error", "Não foi possivel logar com o Google");
            $this->router->redirect("web.login");
        }

        if($code && empty($_SESSION["google_auth"])) {
            try {
                $token = $google->getAccessToken("authorization_code", ["code" => $code]);
                $_SESSION["google_auth"] = serialize($google->getResourceOwner($token));
            } catch(Exception $exception){
                flash("error", "Não foi possivel logar com o Google");
                $this->router->redirect("web.login");
            }
        }

        /** @var $google_user GoogleUser */
        $google_user = unserialize($_SESSION["google_auth"]);
        $user_by_id = (new User())->find("google_id = :google_id", "google_id={$google_user->getId()}")->fetch();

        //Login by Id
        if($user_by_id) {
            unset($_SESSION["google_auth"]);

            $_SESSION["user"] = $user_by_id->id;
            $this->router->redirect("app.home");
        }

        //Login by e-mail
        $user_by_email = (new User())->find("email = :email", "email={$google_user->getEmail()}")->fetch();

        if($user_by_email) {
            flash("info", "Olá {$google_user->getFirstName()}, faça login para conectar seu Google");
            $this->router->redirect("web.login");
        }

        //Register if not
        $link = $this->router->route("web.login");
        flash("info", "Olá {$google_user->getFirstName()}, se já tem uma conta clique em <a href='{$link}'> FAZER LOGIN</a>, ou complete seu cadastro");
        $this->router->redirect("web.register");
    }

    public function socialValidate(User $user): void {
        /**
         * FACEBOOK
         */
        if(!empty($_SESSION["facebook_auth"])){
            /** @var $facebook_user FacebookUser */
            $facebook_user = unserialize($_SESSION["facebook_auth"]);

            $user->facebook_id = $facebook_user->getId();
            $user->photo = $facebook_user->getPictureUrl();
            $user->save();

            unset($_SESSION["facebook_auth"]);
        }

        /**
         * GOOGLE
         */
        if(!empty($_SESSION["google_auth"])){
            /** @var $facebook_user FacebookUser */
            $google_user = unserialize($_SESSION["google_auth"]);

            $user->google_id = $google_user->getId();
            $user->photo = $google_user->getAvatar();
            $user->save();

            unset($_SESSION["google_auth"]);
        }
    }

}