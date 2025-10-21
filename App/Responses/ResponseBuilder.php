<?php
declare(strict_types=1);

namespace App\Responses;

class ResponseBuilder
{
    protected array $response = [
        'status'  => 'ok',
        'message' => null,
        'data'    => null,
        'errors'  => null,
        'meta'    => null,
    ];

    /** @var array<string,string> */
    protected array $headers = [];

    protected int $statusCode = 200;

    public static function ok(): self
    {
        $b = new self();
        $b->response['status'] = 'ok';
        $b->statusCode = 200;
        return $b;
    }

    public static function error(string $message = 'An error occurred'): self
    {
        $b = new self();
        $b->response['status'] = 'error';
        $b->response['message'] = $message;
        $b->statusCode = 400;
        return $b;
    }

    public function withMessage(string $message): self
    {
        $this->response['message'] = $message;
        return $this;
    }

    public function withData($data): self
    {
        $this->response['data'] = $data;
        return $this;
    }

    public function withErrors(array $errors): self
    {
        $this->response['errors'] = $errors;
        return $this;
    }

    public function withMeta(array $meta): self
    {
        $this->response['meta'] = $meta;
        return $this;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->statusCode = $status;
        return $this;
    }

    public function build(): array
    {
        return array_filter($this->response, fn($v) => $v !== null);
    }

    public function json(?int $status = null, ?array $headers = null): void
    {
        if (is_int($status)) {
            $this->statusCode = $status;
        }
        if (is_array($headers)) {
            $this->withHeaders($headers);
        }

        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }

        echo json_encode($this->build(), JSON_UNESCAPED_UNICODE);
    }
}
