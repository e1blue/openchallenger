<?php
//特商法キャンセル
//特商法：メールでご請求いただければ、遅滞なく開示いたします。
//概要：テストのクレジットカード424242424242242
//人数が欲しい
//OGP

define("CONFIG_JSON", "json/config.json");
define("REMOTE_VIEW", "https://raw.githubusercontent.com/openchallenger/openchallenger/master/");

(false !== ($c = config())) && (!isset($_GET["m"])) ? top($c) : $a = (in_array($_GET["m"], array("pay", "admin"), true)) ? $_GET["m"]($c) : top($c);

function top($c) {
    $c["rate"] = ($c["total"] * 100) / intval($c["goal"]);
    $c["total"] = number_format($c["total"]);
    $c["goal"] = number_format($c["goal"]);
    $c["url"] = ((443 !== intval($_SERVER['SERVER_PORT'])) ? "http://" : "https://") . $_SERVER['HTTP_HOST'] . "/" . ($_SERVER['REQUEST_URI']);
    //0123456789012345678901234
    //2006-04-05T01:02:03+00:00
    
    
    $c["left"] = intval((mktime( substr($c["deadline"] , 11, 2 ), substr($c["deadline"] , 14, 2 ), substr($c["deadline"] , 17, 2 ), substr($c["deadline"] , 5, 2 ),
    substr($c["deadline"] , 0, 2 ), substr($c["deadline"] , 0, 4 )) - time()+60*60*24) / (60 * 60 * 24));
    for ($i = 0; $i < 5; $i++) {
        $c["display_plan_" . $i] = (100 < $p = intval($c["plan_" . $i . "_price"])) ? "block;" . sprintf("", $c["plan_" . $i . "_price_formatted"] = number_format($p)) : "none";
    }
    return ($c["left"] > 0) ? render("top.html", $c) : exit("クラウドファウンディングは終了しました。<a href=?m=admin>管理/admin</a>");
}

function pay($c) {
    !($_POST["stripeToken"]) ? exit("ERROR") : "";
    $r = stripe_charge($c["stripe_secret_key"], h($_POST["stripeToken"]), intval($_GET["p"]), $c["title"]);
    ("succeeded" !== $r["status"]) ? exit("credit card error:" . $r["error"]["message"]) : render("thankyou.html", $c);
    set_stripe_total_amount(config());
}

function admin($c) {
    session_start();
    $r = array_merge(array("display_admin_form" => "none", "display_login_form" => "block"), $c);
    (isset($_POST["login_password"])) ? ((0 === strcmp(md5($_POST["login_password"]), $c["password"])) ? $_SESSION["expired"] = time() + 60 * 60 * 8 : printf("<h2>password error!</h2>")) : null;
    if (time() < intval($_SESSION["expired"])) {
        $r = array_merge($r, array("display_admin_form" => "block", "display_login_form" => "none"));
        if (0 === count($_POST)) {
        } else {
            if (0 < strlen($_POST["password"])) {
                $_SESSION["expired"] = ($_POST["password"] == $_POST["password_confirm"]) ? ($c["password"] = md5($_POST["password"])) & 0 : exit("your password can not be confirmed.");
            }
            unset($_POST["password"]);
            unset($_POST["password_confirm"]);
            config(array_merge($c, $_POST));
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
    $c["total"] = 0;
    $c["count"]=0;
    do {
        curl_setopt_array($ch = curl_init(), array( CURLOPT_URL => "https://api.stripe.com/v1/charges?limit=100" . (isset($id) ? "&starting_after=" . $id : ""), CURLOPT_USERPWD => $c["stripe_secret_key"] . ":", CURLOPT_RETURNTRANSFER => true) );
        $r = json_decode(curl_exec($ch), true);
        foreach ($r["data"] as $l) {
            $c["total"]+= intval($l["amount"]);
            $id = $l["id"];
            $c["count"]++;
        }
    } while (1 === intval($r["has_more"]));
    config($c);
}

function render($f, $r) {
    @mkdir("views");
    $b = (file_exists($t = "views/" . $f)) ? file_get_contents($t) : file_get_contents(REMOTE_VIEW . $t);
    (1 > intval(@filesize($t)) && 1024 < strlen($b)) ? file_put_contents($t, $b) : null;
    do {
        $b = str_replace("{{" . key($r) . "}}", (in_array(key($r), array("description", "thankyou")) ? current($r) : h(current($r))), $b);
    } while (false !== next($r));
    echo $b;
    return true;
}

function h($s) {
    return htmlspecialchars(rtrim($s), ENT_QUOTES);
}

function init_config() {
    @mkdir("json");
    chmod("json", 705);
    file_put_contents(CONFIG_JSON, $c = '{"id":"0","title":"Open Challenger","subtitle":"無料でオープンソースのクラウドファウンディングを作った","password":"67f02dedd054bb2a9b356c38f422e2cf","goal":"6000000","total":"0","deadline":"' . date("Y-m-d", time() + 60 * 60 * 24 * 100) . 'T23:55:55","description":"<h3>概要：</h3><ul><li>無料でオープンソースのクラウドファウンディング「Open Challenger」を「心のリハビリ」で作りました。いまさらクラウドファウンディングです。</li><li>Wordpressのように無料のレンタルサーバやクラウドを使えば、技術者でなくても無料・無審査で使えます。</li><li>決済はVISA / Master / AMEX / JCB に対応しています。Stripeを使ってます。</li><li>XAREA/Hostinger/AWS/GCP/Azureの無料枠で使えば動きます。(Heroku, Firebase,GAEは不可)</li><li>もちろん有料のレンタルサーバやIaaS系クラウド、たとえば、さくらインターネット、ロリポップでも動作します。</li><li>今のクラウドファウンディングは手数料20%なので高いという人はおすすめ...かも。</li><li>クレカ決済手数料は<b>3.6%</b>で木曜日末締めの次週の金曜日に銀行振込でStripe社より入金。</li><li>設定はWordpressより楽です。HTMLが分かればデザインもいじれます。</li><li>自分でサーバを立てているので審査はないです。</li><li>日本の法律に準拠(特定商取引法)しています。</li><li>クレジットカードセキュリティは国際基準のPCIDSS準拠です。Stripeに丸投げです。クレジットカード番号などはあなたのサーバーに送信されません。</li><li>無料で設定項目も少ないので一日で始められます。ただ、ポルカ(手数料10%)やmakuake(同20%)やCampfire(同17%)のほうがお手軽ですし、ポルカ以外は各社のスタッフがサポートもしてくれます。人伝えで聞く限り、サポートも人間ですしビジネスなので、一生懸命やってくれるときもありますが、やや放置なときもありますし、お金がかかるときもあります。正直、ケースバイケースなのでクラウドファウンディングの死屍累々と栄枯盛衰を見て、ご判断ください。たぶん、一番安くて低機能なクラウドファウンディングは、これを使うことです。メリットは安く済んでデザインもすべて自分でいじれて審査もないことですが、それ以上のメリットはありません。</li></ul><h3>使い方：</h3><ol><li>ファイルをscpやftpやgitでアップロードする。</li><li>ブラウザでアクセスすると、このサイトと同じものができています。</li><li>下部の[管理]から管理画面に入る。初期パスワードはfukuyukiです。</li><li>管理画面で目標金額や値段やタイトルやパスワードを設定する。</li><li>Stripe.comのアカウントを作り、銀行口座を登録する。審査はないです。</li><li>管理画面でStripe.comの公開鍵、秘密鍵(33文字の文字列)を入れる。</li><li>おわり。</li></ol><h3>セキュリティと法律の話:</h3><ol><li>パスワードは早めに変更してください。<li>リターンが何もないクラウドファウンディングはクレジットカード決済が通らないことがあります。<b>お礼メールでも画像一枚でもいいので購入物を何か明確にしてください。</b>予告なく凍結されることがあります。</li></ol><h3>カスタマイズと運用</h3><ol><li>HTMLが書ける人はtop.htmlを編集すればデザインは好きに変えられます。このページのソースを見ればわかるように、かなり平易なHTMLなので適当に変更すればカッコよくなります。むしろ、誰かカッコイイHTMLを書いて僕にください。</li><li>決済のテストや購入管理画面のプレビューをするときは、Stripeのテスト用秘密鍵、公開鍵を設定してください。</li><li>入金管理などはStripeの管理画面を見てください。</li><li>支援金額の合計値は秘密鍵、公開鍵からStripeからとってきています。</li></ol><h3>心のリハビリ:</h3><p>最近、アウトプットが少ないので、なんでもいいからアウトプットをしようと思った。別にクラウドファウンディングに興味はない。なんか色々心がしぼんできて、創作意欲もないし、このまま腐ってしまう気がした。年を取って、結婚をして、子供ができて、人に会うのが楽しくなくなってきた。新しい技術よりも、今日のスーパーの米や卵の値段や、オムツの残量や、冷蔵庫の中身で何が足りないかとか、お迎えの時間までに現在地から保育園に行く最短ルートのほうが気になる生活になってきて、なんだか心がしぼんできた。娘が生まれた時は毎日がうれしくてたのしくて仕方がなかったけど、2歳近くになって心がしぼんできた。嫁がどこかに旅行に行ってこいというので、ジェットスターのセールで売ってた航空券を買って行ったこともない長崎に行った。理由は安かったから。少しの間、嫁に育児をお願いして、三日ほど滞在して、リフレッシュにはなったけど、そこまでインプットがあった感じはなかった。長崎から東京までの飛行機の中でオフラインで調べないで、何でもいいから3時間でできるものを作ろうと思って、クラウドファウンディングのサイトを作ってみることにした。すぐ作れると思ったから。ユーザからの入力は[購入ボタン]しかないので、Stripeに投げればセキュリティを考えるところもないし、実装工数も少ないからだ。フレームワークもいらない。実際これもファイル４つしかない。ただ、驚くほど、思考力が落ちていて、飛行機の中ではフロントと決済とデータベースの部分しかできなかった。管理画面のほうが工数が多かった。最近のWebとかアプリはフレームワークや変な規制が多くてしんどい。Firebase+Vue.js+Nuxtの組み合わせでなんか作ってたけど調べることが多くて、遅くなって、FireStoreがヘッポコな上に、やっていることはいつものインターネットなので疲れた。そもそも、今のインターネットなんでもありすぎて、特に自分が作らないといけないものなんか何もない。黄金時代は終わった。特にやりたいこともない。","thankyou":"<h1>ありがとうございました<\/h1><p>この度はご支援ありがとうございました。心より感謝いたします。<\/p>","youtube_id":"e9Kdc4P_fDc","stripe_public_key":"pk_test_MIlzjsoAglmbORxqFnjEsRFj","stripe_secret_key":"sk_test_dPYhml9ZW665x9DLjQjh5dJi","plan_0_title":"お礼メール","plan_0_price":"500","plan_0_description":"お礼をメールを差し上げます。文面は「ありがとう」の1行だけです。","plan_1_title":"お礼メール2行","plan_1_price":"1000","plan_1_description":"お礼をメールを差し上げます。文面は「ありがとう」「ありがとう」の2行だけです。","plan_2_title":"お礼メール3行","plan_2_price":"5000","plan_2_description":"お礼をメールを差し上げます。文面は「ありがとう」「ありがとう」「おおきに」の3行だけです。","plan_3_title":"お礼メール4行","plan_3_price":"10000","plan_3_description":"お礼をメールを差し上げます。文面は「もっとくれ」「もっとくれ」「もっとくれ」「もっとくれ」の4行だけです。","plan_4_title":"非表示テスト","plan_4_price":"0","plan_4_description":"非表示テスト。","tk_name":"テスト太郎","tk_charge":"テスト太郎","tk_address":"東京都千代田区永田町1-1-1","tk_price":"各プランにより、Web上に表記","tk_expense":"通信料などはお客様負担","tk_payment":"クレジットカード","tk_enquire":"text@test.com"}');
    set_stripe_total_amount(json_decode($c, true));
    return json_decode(file_get_contents(CONFIG_JSON), true);
}
