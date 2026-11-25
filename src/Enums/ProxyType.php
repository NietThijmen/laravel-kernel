<?php

namespace Nietthijmen\LaravelKernel\Enums;

enum ProxyType: string
{
    case Datacenter = 'datacenter';
    case Isp = 'isp';
    case Residential = 'residential';
    case Mobile = 'mobile';
    case Custom = 'custom';
}
