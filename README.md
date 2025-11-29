# NocoDB Eloquent Driver for Laravel

A Laravel package that provides an Eloquent driver for NocoDB, allowing you to interact with NocoDB tables using standard Eloquent syntax.

## Installation

1. Install via Composer:
```bash
composer require blackstone/laravel-nocodb
```

2. Publish the configuration file:
```bash
php artisan vendor:publish --tag=nocodb-config
```

## Configuration

Add your NocoDB credentials to your `.env` file:

```env
NOCODB_API_URL=https://app.nocodb.com
NOCODB_API_TOKEN=your-api-token
NOCODB_PROJECT=p_xxxx
NOCODB_WORKSPACE=optional
```

## Usage

### Creating a Model

Create a model and use the `NocoModelTrait`. Set the `$table` property to your NocoDB table name (or ID).

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use BlackstonePro\NocoDB\Traits\NocoModelTrait;

class Lead extends Model
{
    use NocoModelTrait;

    protected $table = 'leads'; // Or Table ID
    protected $primaryKey = 'Id'; // Default is Id
    protected $fillable = ['name', 'email', 'status', 'amount'];
}
```

### Querying

You can use standard Eloquent methods:

```php
// Get all leads
$leads = Lead::all();

// Filter and Sort
$leads = Lead::where('status', 'new')
    ->where('amount', '>', 500)
    ->orderBy('CreatedAt', 'desc')
    ->get();

// Pagination
$leads = Lead::paginate(25);

// Find by ID
$lead = Lead::find(1);
```

### Creating Records

```php
$lead = Lead::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'new',
    'amount' => 1000
]);
```

### Updating Records

```php
$lead = Lead::find(1);
$lead->update(['status' => 'contacted']);

// Or via query (requires ID in where clause for now)
Lead::where('Id', 1)->update(['status' => 'contacted']);
```

### Deleting Records

```php
$lead = Lead::find(1);
$lead->delete();

// Or via query
Lead::destroy(1);
```

## Features

- **Eloquent Compatibility**: Works with `where`, `orderBy`, `limit`, `offset`, `paginate`.
- **NocoDB API v2**: Uses the latest NocoDB REST API.
- **Automatic Retries**: Handles transient API failures.
- **Clean Code**: Follows Laravel standards.

## License

MIT
