# Розширення для роботи з API cервіса Monexy

### Встановлення розширення

Бажано встановлювати розширення з допомогою [composer](http://getcomposer.org/download/).

Виконайте команду

```
php composer.phar require richweber/yii2-monexy "*"
```

або добавте

```
"richweber/yii2-monexy": "dev-master"
```

в розділ `require` вашого `composer.json` файла.

### Конфігурація додатка

Приклад конфігурації:

```php
'components' => [
    ...
    'monexy' => [
        'class' => 'richweber\monexy\Monexy',
        'apiName' => 'testAPI',
        'apiPassword' => 'password',
        'requestType' => 'POST',
        'contentType' => 'JSON',
        'enableCrypt' => false,
    ],
    ...
],
```

**Отримання балансу своїх гаманців:**

```php
Yii::$app->monexy->balance();
```

### Документація

- [Технічна документація](https://www.monexy.ua/ua/api)
- [API PHP class](https://www.monexy.ua/ua/api/class)
- [Знайомство з API Monexy](https://monexy.expert/uk/publication/view.html?post=2)

### License

**yii2-monexy** is released under the BSD 3-Clause License. See the bundled `LICENSE.md` for details.
