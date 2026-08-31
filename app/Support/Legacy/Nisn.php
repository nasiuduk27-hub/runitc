<?php

namespace App\Support\Legacy;

class Nisn
{
    public static function encrypt($val1, $val2 = '1C9o7b0a0T3e0b5ak')
    {
        if (empty($val1)) {
            return $val1;
        }

        $val2 = strtoupper($val2);
        $nlen = strlen($val2);
        $nmaxlen = 10240;

        if (strlen($val1) > $nmaxlen) {
            $cval1 = substr($val1, $nmaxlen);
            $val1 = substr($val1, 0, $nmaxlen);
        } else {
            $cval1 = '';
        }

        $cVal = '';
        for ($i = 0; $i < strlen($val1); $i++) {
            $xia = ord($val1[$i]);
            if ($xia >= 43 && $xia <= 255) {
                $keyIndex = ($i + 1) % $nlen;
                $keyChar = $val2[$keyIndex];

                $xib = $xia + (ord($keyChar) % 15);
                if ($xib > 255) {
                    $xib = $xib - 255 + 42;
                }
                $cVal .= chr($xib);
            } else {
                $cVal .= $val1[$i];
            }
        }

        return $cVal.$cval1;
    }

    public static function decrypt($val1, $val2 = '1C9o7b0a0T3e0b5ak')
    {
        if (empty($val1)) {
            return $val1;
        }

        $val2 = strtoupper($val2);
        $nlen = strlen($val2);
        $nmaxlen = 10240;

        if (strlen($val1) > $nmaxlen) {
            $cval1 = substr($val1, $nmaxlen);
            $val1 = substr($val1, 0, $nmaxlen);
        } else {
            $cval1 = '';
        }

        $cVal = '';
        for ($i = 0; $i < strlen($val1); $i++) {
            $xia = ord($val1[$i]);
            if ($xia >= 43 && $xia <= 255) {
                $keyIndex = ($i + 1) % $nlen;
                $keyChar = $val2[$keyIndex];

                $xib = $xia - (ord($keyChar) % 15);
                if ($xib < 43) {
                    $xib = $xib + 255 - 42;
                }
                $cVal .= chr($xib);
            } else {
                $cVal .= $val1[$i];
            }
        }

        return $cVal.$cval1;
    }

    public static function shift($l_kata, $l_shf)
    {
        if (empty($l_kata)) {
            return '';
        }
        $l_shf = (int) $l_shf;

        if ($l_shf != 0) {
            $kanan = ($l_shf > 0);
            $l_shf = abs($l_shf);
            $hsl = $l_kata;
            $x = strlen($hsl);

            for ($i = 1; $i <= $l_shf; $i++) {
                if ($kanan) {
                    $hsl = substr($hsl, -1).substr($hsl, 0, $x - 1);
                } else {
                    $hsl = substr($hsl, 1, $x - 1).substr($hsl, 0, 1);
                }
            }

            return $hsl;
        }

        return $l_kata;
    }

    public static function readTxt($lxfile, $lxline)
    {
        if (! file_exists($lxfile)) {
            return '';
        }

        $ma = file_get_contents($lxfile);
        if (empty($ma)) {
            return '';
        }

        $li = substr($ma, 0, 6);
        $mb = self::shift($li, 3);
        $ma_body = substr($ma, 7);
        $ma_body = self::decrypt($ma_body, $mb);
        $ma_combined = $li.';'.$ma_body;
        $lines = explode(';', $ma_combined);

        if ($lxline > 0 && $lxline <= count($lines)) {
            return $lines[$lxline - 1];
        }

        return '';
    }

    public static function newToDecimal($hexnum): int
    {
        $base62_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $hexnum = trim($hexnum);
        $decnum = 0;

        for ($i = 0; $i < strlen($hexnum); $i++) {
            $char = $hexnum[$i];
            $val = strpos($base62_chars, $char);
            if ($val === false) {
                $val = (int) $char;
            }
            $decnum = $decnum * 62 + $val;
        }

        return (int) $decnum;
    }

    public static function decimalToNew($nTempNum, $nlen): string
    {
        $base62_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $nWorkVal = (int) $nTempNum;
        $ret_str = '';

        if ($nWorkVal == 0) {
            $ret_str = '0';
        } else {
            while ($nWorkVal > 0) {
                $digit = $nWorkVal % 62;
                $ret_str = $base62_chars[$digit].$ret_str;
                $nWorkVal = intdiv($nWorkVal, 62);
            }
        }

        return str_pad($ret_str, $nlen, '0', STR_PAD_LEFT);
    }

    public static function convert($my_input, $m_true)
    {
        if ($m_true) {
            $lkey = 'X0Q1P8W.9O2E3IR6UT4Y5L,A7KSJDHFG-ZMCNVBxqpwoeirutylaksjdhfgzmcnvb';
            $lalpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz,.-';
        } else {
            $lalpha = 'X0Q1P8W.9O2E3IR6UT4Y5L,A7KSJDHFG-ZMCNVBxqpwoeirutylaksjdhfgzmcnvb';
            $lkey = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz,.-';
        }

        for ($i = 0; $i < 5; $i++) {
            $my_input = strtr($my_input, $lalpha, $lkey);
        }

        return $my_input;
    }

    public static function nextFileName($xx_flnm)
    {
        if (! file_exists($xx_flnm)) {
            return $xx_flnm;
        }

        $info = pathinfo($xx_flnm);
        $dir = ($info['dirname'] == '.') ? '' : $info['dirname'].'/';
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';

        $fileke = 1;
        do {
            $new_filename = $dir.$name.'_'.str_pad($fileke, 2, '0', STR_PAD_LEFT).$ext;
            $fileke++;
        } while (file_exists($new_filename));

        return $new_filename;
    }

    public static function timerCounter($ltime, $lcdown)
    {
        $parts = explode(':', $ltime);
        if (count($parts) != 3) {
            return $ltime;
        }

        $ljam = (int) $parts[0];
        $lmnt = (int) $parts[1];
        $ldtk = (int) $parts[2];

        if ($lcdown) {
            if ($ldtk == 0) {
                $ldtk = 59;
                if ($lmnt == 0) {
                    $lmnt = 59;
                    if ($ljam == 0) {
                        $ljam = 23;
                    } else {
                        $ljam--;
                    }
                } else {
                    $lmnt--;
                }
            } else {
                $ldtk--;
            }
        } else {
            $ldtk++;
            if ($ldtk == 60) {
                $ldtk = 0;
                $lmnt++;
            }
            if ($lmnt == 60) {
                $lmnt = 0;
                $ljam++;
            }
            if ($ljam == 24) {
                $ljam = 0;
            }
        }

        return sprintf('%02d:%02d:%02d', $ljam, $lmnt, $ldtk);
    }

    public static function hexToMemo($lhexstr)
    {
        if (is_null($lhexstr) || empty(trim($lhexstr))) {
            return '';
        }

        $result = @hex2bin(trim($lhexstr));

        return $result !== false ? $result : '';
    }
}
