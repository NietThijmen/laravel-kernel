<?php

namespace Nietthijmen\LaravelKernel\Enums;

enum ProxyStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
