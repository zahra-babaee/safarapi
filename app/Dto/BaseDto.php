<?php

namespace App\Dto;

use JsonSerializable;

class BaseDto implements JsonSerializable
{

    public BaseDtoStatusEnum|null $status = null;
    public string|null $message = null;
    public JsonSerializable|\stdClass|array|string|null $data = null;

    /**
     * @param BaseDtoStatusEnum|null $status
     * @param string|null $message
     * @param array|JsonSerializable|\stdClass|string|null $data
     */
    public function __construct(?BaseDtoStatusEnum $status = null, ?string $message = null, array|string|\stdClass|JsonSerializable|null $data = null)
    {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
    }


    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status?->value,
            'message' => $this->message,
            'data' => $this->data
        ];
    }
}

enum BaseDtoStatusEnum : string
{
    case OK = 'OK';
    case ERROR = 'ERROR3';
}
