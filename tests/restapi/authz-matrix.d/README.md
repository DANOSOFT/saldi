# Authorization matrix entries per slice

`MobileAuthorizationMatrixTest` requires every endpoint file under `restapi/endpoints/v1/` to be registered with the exact `requiredRight()` it declares. Slices that add endpoints register them here, one PHP file per slice (for example `t2-b3.php`), so slices that run at the same time never edit the same file.

Each file returns an array keyed by the endpoint path relative to `restapi/endpoints/v1/`:

```php
<?php
return [
    'debitor/open-posts/index.php' => ['module' => 'debitorrapport', 'action' => 'read', 'methods' => ['GET']],
];
```

`module` and `action` must match the endpoint's `requiredRight()` (see `doc/mobile/CONTRACTS.md`); `methods` lists the HTTP methods the endpoint answers.
