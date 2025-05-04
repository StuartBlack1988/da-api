# Hello World PHP API

A simple PHP API with routing capabilities.

## Setup

1. Make sure you have PHP 7.4 or higher installed
2. Install Composer dependencies:
   ```bash
   composer install
   ```

## Running the API

You can run the API using PHP's built-in development server:

```bash
php -S localhost:8000
```

## API Endpoints

- `GET /` - Welcome message
- `GET /hello` - Returns "Hello World!"
- `GET /hello/{name}` - Returns "Hello, {name}!"

## Example Usage

```bash
# Welcome endpoint
curl http://localhost:8000/

# Hello World endpoint
curl http://localhost:8000/hello

# Hello with name endpoint
curl http://localhost:8000/hello/John
```

All responses are in JSON format. 