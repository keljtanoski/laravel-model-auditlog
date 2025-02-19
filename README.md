# Laravel Model Auditlog

[![Latest Version on Packagist](https://img.shields.io/packagist/v/always-open/laravel-model-auditlog.svg?style=flat-square)](https://packagist.org/packages/always-open/laravel-model-auditlog)
[![Build Status](https://img.shields.io/github/workflow/status/always-open/laravel-model-auditlog/tests?style=flat-square)](https://github.com/always-open/laravel-model-auditlog/actions?query=workflow%3Atests)
[![Total Downloads](https://img.shields.io/packagist/dt/always-open/laravel-model-auditlog.svg?style=flat-square)](https://packagist.org/packages/always-open/laravel-model-auditlog)
[![Maintainability](https://api.codeclimate.com/v1/badges/67a16a2e16e938f73304/maintainability)](https://codeclimate.com/github/always-open/laravel-model-auditlog/maintainability)

When modifying a model record, it is nice to have a log of the changes made and who made those changes. There are many packages around this already, but this one is different in that it logs those changes to individual tables for performance and supports real foreign keys.

## Installation

You can install the package via composer:

```bash
composer require always-open/laravel-model-auditlog
```

## Configuration

``` php
php artisan vendor:publish --provider="\AlwaysOpen\AuditLog\AuditLogServiceProvider"
```

Running the above command will publish the config file.

## Usage

After adding the proper fields to your table, add the trait to your model.

``` php
// User model
class User extends Model
{
    use \AlwaysOpen\AuditLog\Traits\AuditLoggable;

```

To generate an auditlog model / migration for your models, use the following command:

```sh
php artisan make:model-auditlog "\App\User"
```

Replace `\App\User` with your own model name. Model / table options can be tweaked in the config file.

If you need to ignore specific fields on your model, extend the `getAuditLogIgnoredFields()` method and return an array of fields.

```php
public function getAuditLogIgnoredFields() : array
{
    return ['posted_at'];
}
```

Using that functionality, you can add more custom logic around what should be logged. An example might be to not log the title changes of a post if the post has not been published yet.
```php
public function getAuditLogIgnoredFields() : array
{
    if ($this->postHasBeenPublished()) {
        return ['title'];
    }

    return [];
}
```

### Working with Pivot Tables

Audit log can also support changes on pivot models as well.

In this example we have a `posts` and `tags` table with a `post_tags` pivot table containing a `post_id` and `tag_id`.

Modify the audit log migration replacing the `subject_id` column to use the two pivot columns. 
```php
Schema::create('post_tag_auditlog', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedInteger('post_id')->index();
    $table->unsignedInteger('tag_id')->index();
    $table->unsignedTinyInteger('event_type')->index();
    $table->unsignedInteger('user_id')->nullable()->index();
    $table->string('field_name')->index();
    $table->text('field_value_old')->nullable();
    $table->text('field_value_new')->nullable();
    $table->timestamp('occurred_at')->index()->default('CURRENT_TIMESTAMP');
});
```

Create a model for the pivot table that extends Laravel's Pivot class. This class must use the AuditLoggablePivot trait and have a defined `$audit_loggable_keys` variable, which is used to map the pivot to the audit log table.
 
```php
class PostTag extends Pivot
{
    use AuditLoggablePivot;

    /**
     * The array keys are the composite key in the audit log
     * table while the pivot table columns are the values.
     *
     * @var array
     */
    protected $audit_loggable_keys = [
        'post_id' => 'post_id',
        'tag_id'  => 'tag_id',
    ];
}
```
Side note: if a column shares the same name in the pivot and a column already in the audit log table (ex: `user_id`), change the name of the column in the audit log table (ex: `audit_user_id`) and define the relationship as `'audit_user_id' => 'user_id'`.

The two models that are joined by the pivot will need to be updated so that events fire on the pivot model. Currently Laravel doesn't support pivot events so a third party package is required.
```php
composer require fico7489/laravel-pivot
```

Have both models use the PivotEventTrait
```php
use Fico7489\Laravel\Pivot\Traits\PivotEventTrait;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use PivotEventTrait;
```

Modify the belongsToMany join on both related models to include the using function along with the pivot model.
In the Post model:
```php
public function tags()
{
    return $this->belongsToMany(Tag::class)
        ->using(PostTag::class);
}
```
In the Tag model:
```php
public function posts()
{
    return $this->belongsToMany(Post::class)
        ->using(PostTag::class);
}
```

When a pivot record is deleted through `detach` or `sync`, an audit log record for each of the keys (ex: `post_id` and `tag_id`) will added to the audit log table. The `field_value_old` will be the id of the record and the `field_value_new` will be null. The records will have an event type of `PIVOT_DELETED` (id: 6). 

If you need to pull the audit logs through the `auditLogs` relationship (ex: $post_tag->auditLogs()->get()), support for composite keys is required.
```php
composer require awobaz/compoships
```
Then use the trait on the pivot audit log model:
```php
use Awobaz\Compoships\Compoships;
use AlwaysOpen\AuditLog\Models\BaseModel;

class PostTagAuditLog extends BaseModel
{
    use Compoships;
```

For a working example of pivots with the audit log, see `laravel-model-auditlog/tests/Fakes`, which contains working migrations and models.

Note:
Both models must use the AuditLoggable trait (ex: Post and Tag) so that `$post->tags()->sync([...])` will work.

### Testing

``` bash
composer test
```

### Using Docker
All assets are set up under the docker-compose.yml file. The first time you run the docker image you must build it with
the following command:
```bash
docker-compose build
```

Then you can bring it up in the background using:
```bash
docker-compose up -d
```

And the image is aliased so you can access its command line via:
```bash
docker exec -it processes-stamp-app /bin/bash
```

From there you can run the tests within an isolated environment

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email @tomschlick / @qschmick instead of using the issue tracker.

## Credits

- [Tom Schlick](https://github.com/tomschlick)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
