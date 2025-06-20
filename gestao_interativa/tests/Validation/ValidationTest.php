<?php

namespace GestaoInterativa\Tests\Validation;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Validation\Validation;
use GestaoInterativa\Exceptions\ValidationException;

class ValidationTest extends TestCase
{
    private $validation;

    protected function setUp(): void
    {
        $this->validation = new Validation();
    }

    public function testValidationCanBeCreated()
    {
        $this->assertInstanceOf(Validation::class, $this->validation);
    }

    public function testValidateRequired()
    {
        $data = ['name' => 'Test'];
        $rules = ['name' => 'required'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['name' => ''];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateEmail()
    {
        $data = ['email' => 'test@example.com'];
        $rules = ['email' => 'email'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['email' => 'invalid-email'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateMin()
    {
        $data = ['password' => '123456'];
        $rules = ['password' => 'min:6'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['password' => '12345'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateMax()
    {
        $data = ['name' => 'Test'];
        $rules = ['name' => 'max:10'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['name' => 'This is a very long name'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateNumeric()
    {
        $data = ['age' => '25'];
        $rules = ['age' => 'numeric'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['age' => 'not-a-number'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateAlpha()
    {
        $data = ['name' => 'Test'];
        $rules = ['name' => 'alpha'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['name' => 'Test123'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateAlphaNumeric()
    {
        $data = ['username' => 'Test123'];
        $rules = ['username' => 'alpha_numeric'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['username' => 'Test@123'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateDate()
    {
        $data = ['birthdate' => '2024-01-01'];
        $rules = ['birthdate' => 'date'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['birthdate' => 'invalid-date'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateCpf()
    {
        $data = ['cpf' => '123.456.789-01'];
        $rules = ['cpf' => 'cpf'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['cpf' => '123.456.789-00'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateCnpj()
    {
        $data = ['cnpj' => '12.345.678/9012-34'];
        $rules = ['cnpj' => 'cnpj'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['cnpj' => '12.345.678/9012-33'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidatePlaca()
    {
        $data = ['placa' => 'ABC1234'];
        $rules = ['placa' => 'placa'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['placa' => 'ABC123'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateIn()
    {
        $data = ['status' => 'active'];
        $rules = ['status' => 'in:active,inactive'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['status' => 'invalid'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateNotIn()
    {
        $data = ['status' => 'pending'];
        $rules = ['status' => 'not_in:active,inactive'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['status' => 'active'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateBetween()
    {
        $data = ['age' => '25'];
        $rules = ['age' => 'between:18,65'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['age' => '17'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateSize()
    {
        $data = ['code' => '123456'];
        $rules = ['code' => 'size:6'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['code' => '12345'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateUrl()
    {
        $data = ['website' => 'http://example.com'];
        $rules = ['website' => 'url'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['website' => 'invalid-url'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateIp()
    {
        $data = ['ip' => '192.168.1.1'];
        $rules = ['ip' => 'ip'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['ip' => 'invalid-ip'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateJson()
    {
        $data = ['config' => '{"key": "value"}'];
        $rules = ['config' => 'json'];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);

        $data = ['config' => 'invalid-json'];
        $this->expectException(ValidationException::class);
        $this->validation->validate($data, $rules);
    }

    public function testValidateMultipleRules()
    {
        $data = [
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => '25'
        ];
        $rules = [
            'name' => 'required|alpha|max:10',
            'email' => 'required|email',
            'age' => 'required|numeric|between:18,65'
        ];

        $result = $this->validation->validate($data, $rules);
        $this->assertTrue($result);
    }

    public function testGetErrors()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        try {
            $this->validation->validate($data, $rules);
        } catch (ValidationException $e) {
            $errors = $this->validation->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertEquals('The name field is required.', $errors['name']);
        }
    }

    public function testGetFirstError()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        try {
            $this->validation->validate($data, $rules);
        } catch (ValidationException $e) {
            $error = $this->validation->getFirstError();
            $this->assertEquals('The name field is required.', $error);
        }
    }

    public function testHasErrors()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        try {
            $this->validation->validate($data, $rules);
        } catch (ValidationException $e) {
            $this->assertTrue($this->validation->hasErrors());
        }
    }

    public function testClearErrors()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        try {
            $this->validation->validate($data, $rules);
        } catch (ValidationException $e) {
            $this->validation->clearErrors();
            $this->assertFalse($this->validation->hasErrors());
        }
    }
} 