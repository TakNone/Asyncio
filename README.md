<p align = 'center'>
  <img src = 'https://skillicons.dev/icons?i=php' alt = 'Asyncio'>
</p>

<h1 align = 'center'>
  Asyncio
</h1>

<p align = 'center'>
  <strong>Modern Async Foundations for PHP</strong>
</p>

<p align = 'center'>
  High-performance asynchronous framework for PHP with support for
  <strong>Revolt</strong> and <strong>Swoole</strong>
</p>

<p align = 'center'>
  <img src = 'https://img.shields.io/github/license/TakNone/asyncio?style=for-the-badge'>
  <img src = 'https://img.shields.io/packagist/php-v/taknone/asyncio?style=for-the-badge&logo=php'>
  <img src = 'https://img.shields.io/packagist/v/taknone/asyncio?style=for-the-badge&logo=composer'>
  <img src = 'https://img.shields.io/github/stars/TakNone/asyncio?style=for-the-badge'>
</p>

* * *

## ✨ Features

- High-performance asynchronous execution for modern PHP projects
- Event-loop driven architecture
- Compatible with **Revolt** and **Swoole**
- Fiber-friendly async design
- Non-blocking file, socket, and stream utilities
- Lightweight helpers for building async-first applications
- MIT licensed and easy to integrate into existing codebases

* * *

## 🛠️ Requirements

- PHP **8.4** or higher
- `revolt/event-loop :^1.0`
- Optional : `ext-swoole` for high-performance coroutine support
- Optional : `ext-pcntl` for better signal handling

* * *

## 📦 Installation

Install via Composer :

```bash
composer require taknone/asyncio
```

### Optional Extensions

```bash
pecl install swoole
```

| Extension | Purpose |
|------------|------------|
| Swoole | High-performance coroutine runtime |
| PCNTL | Better signal handling |

* * *

## 🏁 Getting Started

The library is designed to be used with async-friendly code, event loops, Fibers, and non-blocking I/O

```php
<?php

require 'vendor/autoload.php';

use function Tak\Asyncio\async;
use function Tak\Asyncio\delay;

use Tak\Asyncio\TimeoutCancellation;

$future = async(function () : void {
    echo 'Background task initialized' , PHP_EOL;
    delay(1.5);
    echo 'Background task completed' , PHP_EOL;
});

echo 'Main execution continues without blocking.' , PHP_EOL;

$future->await(new TimeoutCancellation(2));

echo 'Future resolved successfully' , PHP_EOL;
```

* * *

## 💈 Project Structure

This package is autoloaded through PSR-4 with the `Tak\\Asyncio\\` namespace and includes helper files for :

- `src/functions.php`
- `src/File/functions.php`
- `src/Socket/functions.php`
- `src/ByteStream/functions.php`

That layout is ideal for helper-based async APIs that cover core runtime, file, socket, and stream operations

* * *

## ⁉️ Why Asyncio

Asyncio is built for developers who want a clean async layer without sacrificing PHP performance or portability. It is a good fit for :

- async services
- background workers
- socket servers
- file streaming
- high-concurrency I/O
- coroutine-based applications

* * *

## ⚡ Usage Notes

- Use **Revolt** when you want a standards-friendly event loop
- Use **Swoole** when you want coroutine-based high-performance execution
- Keep I/O non-blocking to get the best results
- Prefer async helpers instead of direct blocking calls inside loop-driven code

* * *

## Contributing

Contributions are welcome. Please keep changes consistent with the existing architecture, naming style, and async-first design

* * *


## 📜 License

This project is licensed under the [MIT License](LICENSE)