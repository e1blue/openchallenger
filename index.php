<?php
//ヘドロのようなコードだろ？
define("CONFIG_JSON", "json/config.json");
define("REMOTE_VIEW", "https://raw.githubusercontent.com/openchallenger/openchallenger/master/");

(false !== ($c = config())) && (!isset($_GET["m"])) ? top($c) : $a = (in_array($_GET["m"], array("pay", "admin"), true)) ? $_GET["m"]($c) : top($c);

function top($c) {
    $c["rate"] = ($c["total"] * 100) / intval($c["goal"]);
    $c["total"] = number_format($c["total"]);
    $c["goal"] = number_format($c["goal"]);
    $c["url"] = ((443 !== intval($_SERVER['SERVER_PORT'])) ? "http://" : "https://") . $_SERVER['HTTP_HOST'] . "/" . ($_SERVER['REQUEST_URI']);
    $c["left"] = intval((mktime( substr($c["deadline"] , 11, 2 ), substr($c["deadline"] , 14, 2 ), substr($c["deadline"] , 17, 2 ), substr($c["deadline"] , 5, 2 ),substr($c["deadline"] , 8, 2 ), substr($c["deadline"] , 0, 4 )) - time()) / (60 * 60 * 24));
    for ($i = 0; $i < 5; $i++) {
        $c["display_plan_" . $i] = (100 < $p = intval($c["plan_" . $i . "_price"])) ? "block;" . sprintf("", $c["plan_" . $i . "_price_formatted"] = number_format($p)) : "none";
    }
    return ($c["left"] > 0) ? render("top.html", $c) : render("blank.html", array("contents"=>"<h1>クラウドファウンディングは終了しました。</h1>"));
}

function pay($c) {
    !($_POST["stripeToken"]) ? exit("ERROR") : "";
    $r = stripe_charge($c["stripe_secret_key"], h($_POST["stripeToken"]), intval($_GET["p"]), $c["title"]);
    ("succeeded" !== $r["status"]) ? exit("credit card error:" . $r["error"]["message"]) : render("blank.html", array_merge( $c , array("contents"=>$c["thankyou"])));
    set_stripe_total_amount(config());
}

function admin($c) {
    session_start();
    $r = array_merge(array("display_admin_form" => "none", "display_login_form" => "block"), $c);
    (isset($_POST["login_password"])) ? (( password_verify($_POST["login_password"], $c["password"]) ? $_SESSION["expired"] = time() + 60 * 60 * 8 : printf("<h2>password error!</h2>"))) : null;
    if (time() < intval($_SESSION["expired"])) {
        $r = array_merge($r, array("display_admin_form" => "block", "display_login_form" => "none"));
        if (0 < count($_POST)) {
            if (0 < strlen($_POST["password"])) {
                $_SESSION["expired"] = (0===strcmp($_POST["password"] , $_POST["password_confirm"])) ? ($c["password"] = password_hash($_POST["password"],PASSWORD_DEFAULT)) & 0 : exit( intval(render("blank.html", array("contents"=>"<h2>パスワードが一致しません。</h2>"))));;
            }
            foreach( array("login_password", "password","password_confirm" ) as $l )unset( $_POST[$l] );
            config($c=array_merge($c, $_POST));
            set_stripe_total_amount( $c );
            header("Location: ./?m=admin");
        }
    }
    return render("admin.html", $r);
}

function config($c = null) {
    (null === $c ? ($c = file_exists(CONFIG_JSON) ? json_decode(file_get_contents(CONFIG_JSON), true) : init_config()) : file_put_contents(CONFIG_JSON, json_encode($c, JSON_UNESCAPED_UNICODE)));
    do {
        $c[key($c) ] = htmlspecialchars_decode(current($c));
    } while (false !== next($c));
    return $c;
}

function stripe_charge($apiKey, $token, $amount, $description) {
    curl_setopt_array($ch = curl_init(), array( CURLOPT_URL => "https://api.stripe.com/v1/charges", CURLOPT_USERPWD => $apiKey . ":", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array("amount" => intval($amount), "currency" => "jpy", "description" => $description, "source" => $token), '', '&') ));
    return (json_decode(curl_exec($ch), true));
}

function set_stripe_total_amount($c) {
    $c["total"] = $c["count"]= $c["id"]=0;
    do {
        curl_setopt_array($ch = curl_init(), array( CURLOPT_URL => "https://api.stripe.com/v1/charges?limit=100" . (1<strlen("".$c["id"]) ? "&starting_after=" . $c["id"] : ""), CURLOPT_USERPWD => $c["stripe_secret_key"] . ":", CURLOPT_RETURNTRANSFER => true) );
        $r = json_decode(curl_exec($ch), true);
        foreach ($r["data"] as $l) $c = array_merge( $c , array( "total"=>$c["total"]+intval($l["amount"]) , "count"=>$c["count"]+1 , "id"=>$l["id"]));;
    } while (1 === intval($r["has_more"]));
    config($c);
}

function render($f, $r) {
    @mkdir("views");
    $b = (file_exists($t = "views/" . $f)) ? file_get_contents($t) : file_get_contents(REMOTE_VIEW . $t);
    (1 > intval(@filesize($t)) && 1024 < strlen($b)) ? file_put_contents($t, $b) : null;
    do {
        $b = str_replace("{{" . key($r) . "}}", (in_array(key($r), array("contents","description", "thankyou")) ? current($r) : h(current($r))), $b);
    } while (false !== next($r));
    echo $b;
    return true;
}

function h($s) {
    return htmlspecialchars(rtrim($s), ENT_QUOTES);
}

function init_config() {
    @mkdir("json");
    @chmod("json", 705);
    file_put_contents( CONFIG_JSON, $c = file_get_contents( REMOTE_VIEW."json/config.json"));
    $c=json_decode($c, true);
    $c["deadline"]= date("Y-m-d", time() + 60 * 60 * 24 * 99) . "T23:55:55";
    $c["password"]= password_hash("fukuyuki",PASSWORD_DEFAULT);
    config( $c );
    set_stripe_total_amount( $c );
    return json_decode(file_get_contents(CONFIG_JSON), true);
}

