<?php

// File: classes/Helper.php

require_once __DIR__.'/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

class Helper
{
    public static function kirimEmail($to_email, $to_name, $subject, $body, $is_html = false)
    {
        if (! class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            error_log('PHPMailer tidak tersedia. vendor/autoload.php belum diload.');

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'mail.itc-indonesia.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'itcone-sys@itc-indonesia.com';
            $mail->Password = 'O_&nTPq)VKMw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('itcone-sys@itc-indonesia.com', 'ITCONE System');
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            return true;
        } catch (Throwable $e) {
            error_log("Gagal mengirim email ke {$to_email}: ".$e->getMessage());

            return false;
        }
    }

    public static function pesanLayar($pesan, $tipe = 'error', $redirect = 'back')
    {
        $judul = ($tipe === 'success') ? 'Berhasil!' : 'Oops...';
        $aksi_js = ($redirect === 'back')
            ? 'window.history.back();'
            : "window.location.href = '$redirect';";

        echo "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Pemberitahuan</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: '$tipe',
                    title: '$judul',
                    html: '$pesan',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $aksi_js
                });
            </script>
        </body>
        </html>
        ";
        exit;
    }
}
