# symfony-layout

Version 1.1.0


## Описание возможностей и использования

При формировании страницы сайта основной контент, как правило, занимает только определённую её область,
а остальную часть составляют независимые или слабо зависимые от него блоки. Для удобного описания размещения блоков
следует использовать механизм "раскладок". Тогда описание списка блоков и их порядок записывается в отдельный
файл в формате XML.


### Формат файла раскладки

```xml
<?xml version="1.0" encoding="UTF-8"?>
<layout xmlns="http://symfony.com/schema/dic/layouts" template="@SymfonyLayout/layout.html.twig">
	<stripe>
		<column>
			<block id="container">
				<arg name="comment" value="The place for content from main controller."/>
			</block>
		</column>
	</stripe>
</layout>
```

В данном примере за формирование HTML кода страницы отвечает стандартный шаблон _"@SymfonyLayout/layout.html.twig"_,
который представляет расположение блоков по горизонтальным полосам _(stripe)_, разбитые на колонки _(column)_
с блоками. Простой пример демонстрирует раскладку из одной полосы с одной колонкой, в которой располагается один
блок с основным контентом сайта (на то что блок предназначен для основного контента указывает аттрибут __id__
с значением __container__).

Добавим перед блоком с основным контентом блок главного меню сайта. 

```xml
<?xml version="1.0" encoding="UTF-8"?>
<layout xmlns="http://symfony.com/schema/dic/layouts" template="@SymfonyLayout/layout.html.twig">
	<stripe>
		<column>
			<block uri="/.blocks/main-menu">
				<arg name="active" request="heading"/>
			</block>
			<block id="container"/>
		</column>
	</stripe>
</layout>
```

У данного блока есть URI контроллера который и будет вызван. При этом к нему будет добавлен GET параметер
с именем _active_ и значением, которое будет взято из объекта запроса по ключу _heading_. В примере это будет
символьный код рубрики.

Для более удобного использования можно указать описание блока в отдельном XML файле.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<block xmlns="http://symfony.com/schema/dic/blocks" uri="/.blocks/main-menu">
	<arg name="active" request="heading"/>
</block>
```

Тогда XML раскладки будет иметь следующий вид.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<layout xmlns="http://symfony.com/schema/dic/layouts" template="@SymfonyLayout/layout.html.twig">
	<stripe>
		<column>
			<block extends="block-main-menu.xml"/>
			<block id="container"/>
		</column>
	</stripe>
</layout>
```

При наследовании блока можно переопределить любой аргумент или добавить новый. Значение аргумента можно задавать
следующими образами:

```xml
<block>
	<arg name="from_request_object" request="key"/>
	<arg name="from_configuration" config="key"/>
	<arg name="specific_value" value="key"/>
	<arg name="boolean_value" flag="true"/>
	<arg name="array_value" array="item1, item2"/>
</block>
```

Кроме этого можно добавить атрибуты _default_ и _optional_. При помощи первого добавляется значение "по умолчанию",
которое будет присвоено если нет подходящего ключа в запросе или конфиге. В случае добавления атрибута _optional_
(в качестве его значения выступает _true_ или _false_) возможны два варианта: если нет атрибута _default_, то
при отстутствия ключа в источнике аргумент будет проигнорирован, иначе он будет проигнорирован если его значение
совпадёт с значением по умолчанию.


### Сопоставление раскладки контроллеру

Если в ответе сервера, который получается в результате выполнения действия контроллера, будут открывающие и закрывающие
HTML теги _layout_, то они будут заменены на HTML код раскладки, а контент между ними будет вставлен в раскладке на
место блока с _id_ равным _container_.

Теперь нам надо сопоставить контроллер с именем подходящей раскладки.


#### Первый способ - использовать аннотации

```php
<?php
use Moro\SymfonyLayout\Annotation\Layout;

class Controller {
	/**
	 * @Layout("layout_name")
	 */
	function action() {
		//...
	}
}
```

Одно действие может содержать несколько аннотаций, тогда часть из них должна быть с условием использования. Аннотации
будут проверяться последовательно и в заданном порядке.

    @Layout("layout_name", active="from('2018/10/11 12:00:00', 'Europe/Moscow') and to('2018/10/12')")
    @Layout("layout_name", from="2018/10/11 12:00:00, Europe/Moscow, to="2018/10/12")
    @Layout("layout_name")

Для конвертации даты используется класс _\DateTime_, следовательно можно использовать конструкции вида
```Monday this week 12:00```


#### Второй способ - использование сервиса

```php
<?php
use Moro\SymfonyLayout\Manager\LayoutManager;
class Controller {
	function action(LayoutManager $manager) {
		$manager->setName("layout_name");
		//...
	}
}
```

В данном случае вся логика выбора раскладки возлагается на код действия контроллера.