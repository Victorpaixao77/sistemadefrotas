<?php

namespace GestaoInterativa\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Helpers\Helpers;

class HelpersTest extends TestCase
{
    public function testFormatDate()
    {
        $date = '2024-01-01';
        $formatted = Helpers::formatDate($date);
        $this->assertEquals('01/01/2024', $formatted);
    }

    public function testFormatDateTime()
    {
        $dateTime = '2024-01-01 12:00:00';
        $formatted = Helpers::formatDateTime($dateTime);
        $this->assertEquals('01/01/2024 12:00', $formatted);
    }

    public function testFormatCurrency()
    {
        $value = 1000.50;
        $formatted = Helpers::formatCurrency($value);
        $this->assertEquals('R$ 1.000,50', $formatted);
    }

    public function testFormatNumber()
    {
        $number = 1000.50;
        $formatted = Helpers::formatNumber($number);
        $this->assertEquals('1.000,50', $formatted);
    }

    public function testFormatPhone()
    {
        $phone = '11999999999';
        $formatted = Helpers::formatPhone($phone);
        $this->assertEquals('(11) 99999-9999', $formatted);
    }

    public function testFormatCpf()
    {
        $cpf = '12345678901';
        $formatted = Helpers::formatCpf($cpf);
        $this->assertEquals('123.456.789-01', $formatted);
    }

    public function testFormatCnpj()
    {
        $cnpj = '12345678901234';
        $formatted = Helpers::formatCnpj($cnpj);
        $this->assertEquals('12.345.678/9012-34', $formatted);
    }

    public function testFormatPlaca()
    {
        $placa = 'ABC1234';
        $formatted = Helpers::formatPlaca($placa);
        $this->assertEquals('ABC-1234', $formatted);
    }

    public function testSanitizeString()
    {
        $string = '<script>alert("test")</script>';
        $sanitized = Helpers::sanitizeString($string);
        $this->assertEquals('alert("test")', $sanitized);
    }

    public function testValidateEmail()
    {
        $validEmail = 'test@example.com';
        $invalidEmail = 'invalid-email';

        $this->assertTrue(Helpers::validateEmail($validEmail));
        $this->assertFalse(Helpers::validateEmail($invalidEmail));
    }

    public function testValidateCpf()
    {
        $validCpf = '123.456.789-01';
        $invalidCpf = '123.456.789-00';

        $this->assertTrue(Helpers::validateCpf($validCpf));
        $this->assertFalse(Helpers::validateCpf($invalidCpf));
    }

    public function testValidateCnpj()
    {
        $validCnpj = '12.345.678/9012-34';
        $invalidCnpj = '12.345.678/9012-33';

        $this->assertTrue(Helpers::validateCnpj($validCnpj));
        $this->assertFalse(Helpers::validateCnpj($invalidCnpj));
    }

    public function testValidatePlaca()
    {
        $validPlaca = 'ABC1234';
        $invalidPlaca = 'ABC123';

        $this->assertTrue(Helpers::validatePlaca($validPlaca));
        $this->assertFalse(Helpers::validatePlaca($invalidPlaca));
    }

    public function testGenerateRandomString()
    {
        $length = 10;
        $randomString = Helpers::generateRandomString($length);

        $this->assertEquals($length, strlen($randomString));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $randomString);
    }

    public function testEncryptAndDecrypt()
    {
        $data = 'test data';
        $key = 'secret key';

        $encrypted = Helpers::encrypt($data, $key);
        $decrypted = Helpers::decrypt($encrypted, $key);

        $this->assertEquals($data, $decrypted);
    }

    public function testGetClientIp()
    {
        $ip = Helpers::getClientIp();
        $this->assertMatchesRegularExpression('/^(\d{1,3}\.){3}\d{1,3}$/', $ip);
    }

    public function testGetUserAgent()
    {
        $userAgent = Helpers::getUserAgent();
        $this->assertIsString($userAgent);
    }

    public function testIsAjaxRequest()
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(Helpers::isAjaxRequest());

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse(Helpers::isAjaxRequest());
    }

    public function testRedirect()
    {
        $url = 'http://example.com';
        
        ob_start();
        Helpers::redirect($url);
        $output = ob_get_clean();

        $this->assertStringContainsString('Location: ' . $url, $output);
    }

    public function testGetBaseUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/sistema-frotas';

        $baseUrl = Helpers::getBaseUrl();
        $this->assertEquals('http://localhost/sistema-frotas', $baseUrl);
    }

    public function testGetCurrentUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/sistema-frotas/test';

        $currentUrl = Helpers::getCurrentUrl();
        $this->assertEquals('http://localhost/sistema-frotas/test', $currentUrl);
    }

    public function testGetFileExtension()
    {
        $filename = 'test.jpg';
        $extension = Helpers::getFileExtension($filename);
        $this->assertEquals('jpg', $extension);
    }

    public function testGetFileSize()
    {
        $size = 1024;
        $formatted = Helpers::getFileSize($size);
        $this->assertEquals('1 KB', $formatted);
    }

    public function testGetMimeType()
    {
        $filename = 'test.jpg';
        $mimeType = Helpers::getMimeType($filename);
        $this->assertEquals('image/jpeg', $mimeType);
    }

    public function testIsImage()
    {
        $filename = 'test.jpg';
        $this->assertTrue(Helpers::isImage($filename));

        $filename = 'test.pdf';
        $this->assertFalse(Helpers::isImage($filename));
    }

    public function testIsPdf()
    {
        $filename = 'test.pdf';
        $this->assertTrue(Helpers::isPdf($filename));

        $filename = 'test.jpg';
        $this->assertFalse(Helpers::isPdf($filename));
    }

    public function testGetFileIcon()
    {
        $filename = 'test.jpg';
        $icon = Helpers::getFileIcon($filename);
        $this->assertEquals('fa-file-image', $icon);

        $filename = 'test.pdf';
        $icon = Helpers::getFileIcon($filename);
        $this->assertEquals('fa-file-pdf', $icon);
    }
} 