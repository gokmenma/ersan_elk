<?php

require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
use App\Model\UserModel;
use App\Helper\Helper;


$User = new UserModel();

// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect him to index page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index?p=home");
    exit;
}
// Include config file
//require_once "layouts/config.php";

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";



// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Kullanıcı adı giriniz.";
    } else {
        $username = trim($_POST["username"]);
    }


    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Şifrenizi giriniz.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {

        //user control
        $user = $User->checkUser($username);


        // Check if username exists, if yes then verify password
        if ($user) {

            //Helper::dd($user);

            $hashed_password = $user->password;


            if (password_verify($password, $hashed_password)) {
                // Password is correct, so start a new session
                // session_start();

                // Store data in session variables
                $_SESSION["loggedin"] = true;

                $_SESSION["user"] = $user;
                $_SESSION["user_id"] = $user->id;
                $_SESSION["id"] = $user->id;
                $_SESSION["owner_id"] = $user->owner_id;
                $_SESSION["username"] = $username;
                $_SESSION["user_full_name"] = $user->UserName;
                //sube_id
                $_SESSION["sube_id"] = $user->sube_id;

                // Redirect user to welcome page
                header("location: firma-secim.php");
            } else {
                // Display an error message if password is not valid
                $password_err = "Hatalı şifre girdiniz.";
            }
        } else {
            $username_err = "Kullanıcı bulunamadı";

        }

    }


}
?>




<?php include 'layouts/main.php'; ?>

<head>

    <title>Giriş Yap | Ersan Elektrik </title>

    <?php include 'layouts/head.php'; ?>

    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-xxl-3 col-lg-4 col-md-5">
                <div class="auth-full-page-content d-flex p-sm-5 p-4">
                    <div class="w-100">
                        <div class="d-flex flex-column h-100">
                            <div class="mb-4 mb-md-5 text-center">
                                <a href="/" class="d-block auth-logo">
                                    <img src="assets/images/logo.png" alt="" height="28">
                                    <p class="logo-txt">Personel Yönetimi</p>

                                </a>
                            </div>
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-0">Tekrar Hoşgeldin !</h5>
                                    <p class="text-muted mt-2">Devam etmek için oturum açın</p>
                                </div>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                    <div class="form-floating form-floating-custom mb-4">
                                        <input type="text"
                                            class="form-control <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>"
                                            id="\username" name="username" value="<?php echo $username; ?>"
                                            placeholder="Kullanıcı Adı Giriniz!">
                                        <label for="input-username">Kullanıcı Adı, Telefon veya Email</label>
                                        <span class="text-danger"><?php echo $username_err; ?></span>
                                        <div class="form-floating-icon">
                                            <i data-feather="users"></i>
                                        </div>
                                    </div>

                                    <div class="form-floating form-floating-custom mb-4 auth-pass-inputgroup">
                                        <input type="password" class="form-control pe-5" id="password" name="password"
                                            value="" placeholder="Şifre Giriniz!">
                                        <span class="text-danger"><?php echo $password_err; ?></span>
                                        <button type="button"
                                            class="btn btn-link position-absolute h-100 end-0 top-0 <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>"
                                            id="password-addon">
                                            <i class="mdi mdi-eye-outline font-size-18 text-muted"></i>
                                        </button>
                                        <label for="input-password">Şifre</label>
                                        <div class="form-floating-icon">
                                            <i data-feather="lock"></i>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col">
                                            <div class="form-check font-size-15">
                                                <input class="form-check-input" type="checkbox" id="remember-check">
                                                <label class="form-check-label font-size-13" for="remember-check">
                                                    Beni Hatırla
                                                </label>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="mb-3">
                                        <button class="btn btn-primary w-100 waves-effect waves-light"
                                            type="submit">Giriş Yap</button>
                                    </div>
                                </form>


                            </div>
                            <div class="mt-4 mt-md-5 text-center">
                                <p class="mb-0">©
                                    <script>
                                        document.write(new Date().getFullYear())
                                    </script> Ersan Elektrik <i class="mdi mdi-heart text-danger"></i> by
                                    mbeyazilim
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end auth full page content -->
            </div>
            <!-- end col -->
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay"></div>
                    <ul class="bg-bubbles">
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                    </ul>
                    <!-- end bubble effect -->
                    <div class="row justify-content-center align-items-end w-100">
                        <div class="col-xl-7">
                            <div class="p-0 p-sm-4 px-xl-0">
                                <div id="reviewcarouselIndicators" class="carousel slide" data-bs-ride="carousel">
                                    <div
                                        class="carousel-indicators auth-carousel carousel-indicators-rounded justify-content-center mb-0">
                                        <button type="button" data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="0" class="active" aria-current="true"
                                            aria-label="Slide 1">
                                            <img src="assets/images/img-2.jpg"
                                                class="avatar-md img-fluid rounded-circle d-block" alt="...">
                                        </button>
                                        <button type="button" data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="1" aria-label="Slide 2">
                                            <img src="assets/images/profile-bg-1.jpg"
                                                class="avatar-md img-fluid rounded-circle d-block" alt="...">
                                        </button>
                                        <button type="button" data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="2" aria-label="Slide 3">
                                            <img src="assets/images/bg-3.jpg"
                                                class="avatar-md img-fluid rounded-circle d-block" alt="...">
                                        </button>
                                    </div>
                                    <!-- end carouselIndicators -->
                                    <div class="carousel-inner ">
                                        <div class="carousel-item active">
                                            <div class="testi-contain text-center text-white">
                                                <i class="bx bxs-quote-alt-left text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">“Personellerinizin tüm
                                                    işlemleri tek panelde”
                                                </h4>
                                                <div class="mt-4 pt-1 pb-5 mb-5">
                                                    <h5 class="font-size-16 text-white">Ersan Elektrik
                                                    </h5>
                                                    <p class="mb-0 text-white-50">Merkez </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div class="testi-contain text-center text-white">
                                                <i class="bx bxs-quote-alt-left text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">“Demirbaş ve Stok
                                                    Takiplerini eksiksiz yapın”</h4>
                                                <div class="mt-4 pt-1 pb-5 mb-5">
                                                    <h5 class="font-size-16 text-white">Ersan Elektrik
                                                    </h5>
                                                    <p class="mb-0 text-white-50">Merkez</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div class="testi-contain text-center text-white">
                                                <i class="bx bxs-quote-alt-left text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">“Karışık raporlamalarla
                                                    hiç uğraşmayın.”</h4>
                                                <div class="mt-4 pt-1 pb-5 mb-5">
                                                    <h5 class="font-size-16 text-white">Ersan Elektrik
                                                    </h5>
                                                    <p class="mb-0 text-white-50">Merkez</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end carousel-inner -->
                                </div>
                                <!-- end review carousel -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end col -->
        </div>
        <!-- end row -->
    </div>
    <!-- end container fluid -->
</div>

<?php include 'layouts/vendor-scripts.php'; ?>


</body>

</html>