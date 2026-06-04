<?php
declare(strict_types=1);

class PingController extends BaseController
{
    public function index(array $params = []): void
    {
        $this->jsonResponse(
            ['status' => 'ok', 'time' => date('Y-m-d H:i:s')],
            'API funcionando correctamente.'
        );
    }
}
