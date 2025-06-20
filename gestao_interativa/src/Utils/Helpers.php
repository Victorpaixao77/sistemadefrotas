<?php
namespace GestaoInterativa\Utils;

class Helpers {
    /**
     * Formata um valor monetário
     */
    public static function formatMoney($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Formata uma data
     */
    public static function formatDate($date, $format = 'd/m/Y') {
        if (!$date) return null;
        return date($format, strtotime($date));
    }

    /**
     * Formata uma data e hora
     */
    public static function formatDateTime($date, $format = 'd/m/Y H:i:s') {
        if (!$date) return null;
        return date($format, strtotime($date));
    }

    /**
     * Formata um número de quilômetros
     */
    public static function formatKm($value) {
        return number_format($value, 0, ',', '.') . ' km';
    }

    /**
     * Formata um número de telefone
     */
    public static function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
        }
        return $phone;
    }

    /**
     * Formata um CPF
     */
    public static function formatCpf($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    /**
     * Formata um CNPJ
     */
    public static function formatCnpj($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    /**
     * Formata uma placa de veículo
     */
    public static function formatPlaca($placa) {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
        if (strlen($placa) == 7) {
            return substr($placa, 0, 3) . '-' . substr($placa, 3, 4);
        }
        return $placa;
    }

    /**
     * Calcula a diferença entre duas datas em dias
     */
    public static function dateDiff($date1, $date2) {
        $date1 = new \DateTime($date1);
        $date2 = new \DateTime($date2);
        $diff = $date1->diff($date2);
        return $diff->days;
    }

    /**
     * Calcula a diferença entre duas datas em meses
     */
    public static function monthDiff($date1, $date2) {
        $date1 = new \DateTime($date1);
        $date2 = new \DateTime($date2);
        $diff = $date1->diff($date2);
        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Calcula a diferença entre duas datas em anos
     */
    public static function yearDiff($date1, $date2) {
        $date1 = new \DateTime($date1);
        $date2 = new \DateTime($date2);
        $diff = $date1->diff($date2);
        return $diff->y;
    }

    /**
     * Gera um token aleatório
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Sanitiza uma string
     */
    public static function sanitize($string) {
        return htmlspecialchars(strip_tags(trim($string)));
    }

    /**
     * Valida um email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida um CPF
     */
    public static function validateCpf($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return false;
        }
        
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Valida um CNPJ
     */
    public static function validateCnpj($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        for ($i = 0, $j = 5, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $rest = $sum % 11;
        if ($cnpj[12] != ($rest < 2 ? 0 : 11 - $rest)) {
            return false;
        }
        
        for ($i = 0, $j = 6, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $rest = $sum % 11;
        return $cnpj[13] == ($rest < 2 ? 0 : 11 - $rest);
    }

    /**
     * Valida uma placa de veículo
     */
    public static function validatePlaca($placa) {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
        
        if (strlen($placa) != 7) {
            return false;
        }

        // Formato antigo: ABC1234
        if (preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
            return true;
        }

        // Formato Mercosul: ABC1D23
        if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
            return true;
        }

        return false;
    }

    /**
     * Gera um slug a partir de uma string
     */
    public static function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text ?: 'n-a';
    }

    /**
     * Gera um nome de arquivo único
     */
    public static function generateUniqueFilename($extension) {
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Verifica se um arquivo é uma imagem
     */
    public static function isImage($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        return in_array($file['type'], $allowed);
    }

    /**
     * Verifica se um arquivo é um PDF
     */
    public static function isPdf($file) {
        return $file['type'] === 'application/pdf';
    }

    /**
     * Verifica se um arquivo é um documento
     */
    public static function isDocument($file) {
        $allowed = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        return in_array($file['type'], $allowed);
    }

    /**
     * Redimensiona uma imagem
     */
    public static function resizeImage($source, $destination, $maxWidth, $maxHeight) {
        list($width, $height) = getimagesize($source);
        
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = $width * $ratio;
        $newHeight = $height * $ratio;
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        $sourceImage = imagecreatefromjpeg($source);
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        imagejpeg($newImage, $destination, 90);
        
        imagedestroy($newImage);
        imagedestroy($sourceImage);
    }

    public static function formatNumber($value, $decimals = 2) {
        return number_format($value, $decimals, ',', '.');
    }

    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateDate($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public static function validateDateTime($datetime) {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }

    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function mask($str, $mask) {
        $str = str_replace(" ", "", $str);
        for ($i = 0; $i < strlen($str); $i++) {
            $mask[strpos($mask, "#")] = $str[$i];
        }
        return $mask;
    }

    public static function getAge($birthdate) {
        $birthdate = new \DateTime($birthdate);
        $today = new \DateTime();
        $age = $today->diff($birthdate);
        return $age->y;
    }

    public static function getDaysBetween($start, $end) {
        $start = new \DateTime($start);
        $end = new \DateTime($end);
        $interval = $start->diff($end);
        return $interval->days;
    }

    public static function getMonthsBetween($start, $end) {
        $start = new \DateTime($start);
        $end = new \DateTime($end);
        $interval = $start->diff($end);
        return ($interval->y * 12) + $interval->m;
    }

    public static function getYearsBetween($start, $end) {
        $start = new \DateTime($start);
        $end = new \DateTime($end);
        $interval = $start->diff($end);
        return $interval->y;
    }

    public static function isWeekend($date) {
        $dayOfWeek = date('N', strtotime($date));
        return ($dayOfWeek >= 6);
    }

    public static function isHoliday($date) {
        $holidays = [
            '01-01', // Ano Novo
            '04-21', // Tiradentes
            '05-01', // Dia do Trabalho
            '09-07', // Independência do Brasil
            '10-12', // Nossa Senhora Aparecida
            '11-02', // Finados
            '11-15', // Proclamação da República
            '12-25', // Natal
        ];
        return in_array(date('m-d', strtotime($date)), $holidays);
    }

    public static function isBusinessDay($date) {
        return !self::isWeekend($date) && !self::isHoliday($date);
    }

    public static function getNextBusinessDay($date) {
        $nextDay = date('Y-m-d', strtotime($date . ' +1 day'));
        while (!self::isBusinessDay($nextDay)) {
            $nextDay = date('Y-m-d', strtotime($nextDay . ' +1 day'));
        }
        return $nextDay;
    }

    public static function getPreviousBusinessDay($date) {
        $previousDay = date('Y-m-d', strtotime($date . ' -1 day'));
        while (!self::isBusinessDay($previousDay)) {
            $previousDay = date('Y-m-d', strtotime($previousDay . ' -1 day'));
        }
        return $previousDay;
    }

    public static function getBusinessDaysBetween($start, $end) {
        $start = new \DateTime($start);
        $end = new \DateTime($end);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);
        
        $businessDays = 0;
        foreach ($period as $date) {
            if (self::isBusinessDay($date->format('Y-m-d'))) {
                $businessDays++;
            }
        }
        
        return $businessDays;
    }
} 