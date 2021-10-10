<?php

namespace LdapRecord\Tests\Query;

use DateTime;
use LdapRecord\Connection;
use LdapRecord\LdapRecordException;
use LdapRecord\Query\Builder;
use LdapRecord\Query\MultipleObjectsFoundException;
use LdapRecord\Query\ObjectsNotFoundException;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class BuilderTest extends TestCase
{
    protected function newBuilder()
    {
        return new Builder(new Connection([], (new LdapFake())));
    }

    public function test_builder_always_has_default_filter()
    {
        $b = $this->newBuilder();

        $this->assertEquals('(objectclass=*)', $b->getQuery());
    }

    public function test_select_array()
    {
        $b = $this->newBuilder();

        $b->select(['testing']);

        $this->assertEquals([
            'testing',
            'objectclass',
        ], $b->getSelects());
    }

    public function test_select_string()
    {
        $b = $this->newBuilder();

        $b->select('testing');

        $this->assertEquals([
            'testing',
            'objectclass',
        ], $b->getSelects());
    }

    public function test_select_empty_string()
    {
        $b = $this->newBuilder();

        $b->select('');

        $this->assertEquals([
            '',
            'objectclass',
        ], $b->getSelects());
    }

    public function test_has_selects()
    {
        $b = $this->newBuilder();

        $b->select('test');

        $this->assertTrue($b->hasSelects());
    }

    public function test_add_filter()
    {
        $b = $this->newBuilder();

        $b->addFilter('and', [
            'field' => 'cn',
            'operator' => '=',
            'value' => 'John Doe',
        ]);

        $this->assertEquals('(cn=John Doe)', $b->getQuery());
        $this->assertEquals('(cn=John Doe)', $b->getUnescapedQuery());
    }

    public function test_adding_filter_with_invalid_bindings_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Missing 'value' key.
        $this->newBuilder()->addFilter('and', [
            'field' => 'cn',
            'operator' => '=',
        ]);
    }

    public function test_adding_invalid_filter_type_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->newBuilder()->addFilter('non-existent', [
            'field' => 'cn',
            'operator' => '=',
            'value' => 'John Doe',
        ]);
    }

    public function test_get_filters()
    {
        $b = $this->newBuilder();

        $b->where('foo', '=', 'bar');
        $b->orWhere('baz', '=', 'foo');

        $this->assertEquals([
            'and' => [[
                'field' => 'foo',
                'operator' => '=',
                'value' => '\62\61\72',
            ]],
            'or' => [[
                'field' => 'baz',
                'operator' => '=',
                'value' => '\66\6f\6f',
            ]],
            'raw' => [],
        ], $b->getFilters());
    }

    public function test_clear_filters()
    {
        $b = $this->newBuilder();

        $b->addFilter('and', [
            'field' => 'cn',
            'operator' => '=',
            'value' => 'John Doe',
        ]);

        $this->assertEquals('(cn=John Doe)', $b->getQuery());
        $this->assertEquals('(objectclass=*)', $b->clearFilters()->getQuery());
    }

    public function test_where()
    {
        $b = $this->newBuilder();

        $b->where('cn', '=', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
    }

    public function test_where_with_array()
    {
        $b = $this->newBuilder();

        $b->where([
            'cn' => 'test',
            'name' => 'test',
        ]);

        $whereOne = $b->filters['and'][0];

        $this->assertEquals('cn', $whereOne['field']);
        $this->assertEquals('=', $whereOne['operator']);
        $this->assertEquals('\74\65\73\74', $whereOne['value']);

        $whereTwo = $b->filters['and'][1];

        $this->assertEquals('name', $whereTwo['field']);
        $this->assertEquals('=', $whereTwo['operator']);
        $this->assertEquals('\74\65\73\74', $whereTwo['value']);
    }

    public function test_where_with_nested_arrays()
    {
        $b = $this->newBuilder();

        $b->where([
            ['cn', '=', 'test'],
            ['whencreated', '>=', 'test'],
        ]);

        $whereOne = $b->filters['and'][0];

        $this->assertEquals('cn', $whereOne['field']);
        $this->assertEquals('=', $whereOne['operator']);
        $this->assertEquals('\74\65\73\74', $whereOne['value']);

        $whereTwo = $b->filters['and'][1];

        $this->assertEquals('whencreated', $whereTwo['field']);
        $this->assertEquals('>=', $whereTwo['operator']);
        $this->assertEquals('\74\65\73\74', $whereTwo['value']);

        $this->assertEquals('(&(cn=test)(whencreated>=test))', $b->getUnescapedQuery());
    }

    public function test_where_contains()
    {
        $b = $this->newBuilder();

        $b->whereContains('cn', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('contains', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(cn=*test*)', $b->getUnescapedQuery());
    }

    public function test_where_starts_with()
    {
        $b = $this->newBuilder();

        $b->whereStartsWith('cn', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('starts_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(cn=test*)', $b->getUnescapedQuery());
    }

    public function test_where_not_starts_with()
    {
        $b = $this->newBuilder();

        $b->whereNotStartsWith('cn', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('not_starts_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(!(cn=test*))', $b->getUnescapedQuery());
    }

    public function test_where_ends_with()
    {
        $b = $this->newBuilder();

        $b->whereEndsWith('cn', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('ends_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(cn=*test)', $b->getUnescapedQuery());
    }

    public function test_where_not_ends_with()
    {
        $b = $this->newBuilder();

        $b->whereNotEndsWith('cn', 'test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('not_ends_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(!(cn=*test))', $b->getUnescapedQuery());
    }

    public function test_where_deleted()
    {
        $b = $this->newBuilder();

        $b->whereDeleted();

        $this->assertEquals([
            '1.2.840.113556.1.4.417' => ['oid' => '1.2.840.113556.1.4.417', 'isCritical' => true, 'value' => null],
        ], $b->controls);

        $this->assertEquals('(isDeleted=TRUE)', $b->getUnescapedQuery());
    }

    public function test_where_between()
    {
        $from = (new DateTime('October 1st 2016'))->format('YmdHis.0\Z');
        $to = (new DateTime('January 1st 2017'))->format('YmdHis.0\Z');

        $b = $this->newBuilder();

        $b->whereBetween('whencreated', [$from, $to]);

        $this->assertEquals('(&(whencreated>=20161001000000.0Z)(whencreated<=20170101000000.0Z))', $b->getUnescapedQuery());
    }

    public function test_or_where()
    {
        $b = $this->newBuilder();

        $b->orWhere('cn', '=', 'test');

        $where = $b->filters['or'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
    }

    public function test_or_where_with_one_where_should_be_converted_to_single_or_filter()
    {
        $b = $this->newBuilder()
            ->where('foo', '=', 'bar')
            ->orWhere('baz', '=', 'zax');

        $this->assertEquals('(|(foo=\62\61\72)(baz=\7a\61\78))', $b->getQuery());
    }

    public function test_multiple_or_wheres_should_be_single_statement()
    {
        $b = $this->newBuilder()
            ->orWhere('foo', '=', 'bar')
            ->orWhere('baz', '=', 'zax');

        $this->assertEquals('(|(foo=\62\61\72)(baz=\7a\61\78))', $b->getQuery());
    }

    public function test_multiple_wheres_with_or_wheres()
    {
        $b = $this->newBuilder()
            ->where('baz', '=', 'zax')
            ->where('lao', '=', 'zen')
            ->orWhere('foo', '=', 'bar')
            ->orWhere('zue', '=', 'lea');

        $this->assertEquals('(&(baz=zax)(lao=zen)(|(foo=bar)(zue=lea)))', $b->getUnescapedQuery());
    }

    public function test_or_where_with_array()
    {
        $b = $this->newBuilder();

        $b->orWhere([
            'cn' => 'test',
            'name' => 'test',
        ]);

        $whereOne = $b->filters['or'][0];

        $this->assertEquals('cn', $whereOne['field']);
        $this->assertEquals('=', $whereOne['operator']);
        $this->assertEquals('\74\65\73\74', $whereOne['value']);

        $whereTwo = $b->filters['or'][1];

        $this->assertEquals('name', $whereTwo['field']);
        $this->assertEquals('=', $whereTwo['operator']);
        $this->assertEquals('\74\65\73\74', $whereTwo['value']);

        $this->assertEquals('(|(cn=test)(name=test))', $b->getUnescapedQuery());
    }

    public function test_or_where_with_nested_arrays()
    {
        $b = $this->newBuilder();

        $b->orWhere([
            ['one', '=', 'one'],
            ['two', 'contains', 'two'],
            ['three', '*'],
        ]);

        $this->assertEquals('(|(one=one)(two=*two*)(three=*))', $b->getUnescapedQuery());
    }

    public function test_or_where_contains()
    {
        $b = $this->newBuilder();

        $b
            ->whereContains('name', 'test')
            ->orWhereContains('cn', 'test');

        $where = $b->filters['or'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('contains', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);

        $this->assertEquals('(|(name=*test*)(cn=*test*))', $b->getUnescapedQuery());
    }

    public function test_or_where_starts_with()
    {
        $b = $this->newBuilder();

        $b
            ->whereStartsWith('name', 'test')
            ->orWhereStartsWith('cn', 'test');

        $where = $b->filters['or'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('starts_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(|(name=test*)(cn=test*))', $b->getUnescapedQuery());
    }

    public function test_or_where_ends_with()
    {
        $b = $this->newBuilder();

        $b
            ->whereEndsWith('name', 'test')
            ->orWhereEndsWith('cn', 'test');

        $where = $b->filters['or'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('ends_with', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
        $this->assertEquals('(|(name=*test)(cn=*test))', $b->getUnescapedQuery());
    }

    public function test_where_invalid_operator()
    {
        $this->expectException(\InvalidArgumentException::class);

        $b = $this->newBuilder();

        $b->where('field', 'invalid', 'value');
    }

    public function test_or_where_invalid_operator()
    {
        $this->expectException(\InvalidArgumentException::class);

        $b = $this->newBuilder();

        $b->orWhere('field', 'invalid', 'value');
    }

    public function test_built_where()
    {
        $b = $this->newBuilder();

        $b->where('field', '=', 'value');

        $this->assertEquals('(field=value)', $b->getUnescapedQuery());
    }

    public function test_built_wheres()
    {
        $b = $this->newBuilder();

        $b->where('field', '=', 'value');

        $b->where('other', '=', 'value');

        $this->assertEquals('(&(field=value)(other=value))', $b->getUnescapedQuery());
    }

    public function test_built_where_starts_with()
    {
        $b = $this->newBuilder();

        $b->whereStartsWith('field', 'value');

        $this->assertEquals('(field=value*)', $b->getUnescapedQuery());
    }

    public function test_built_where_ends_with()
    {
        $b = $this->newBuilder();

        $b->whereEndsWith('field', 'value');

        $this->assertEquals('(field=*value)', $b->getUnescapedQuery());
    }

    public function test_built_where_contains()
    {
        $b = $this->newBuilder();

        $b->whereContains('field', 'value');

        $this->assertEquals('(field=*value*)', $b->getUnescapedQuery());
    }

    public function test_built_or_where()
    {
        $b = $this->newBuilder();

        $b->orWhere('field', '=', 'value');

        $this->assertEquals('(field=value)', $b->getUnescapedQuery());
    }

    public function test_built_or_wheres()
    {
        $b = $this->newBuilder();

        $b->orWhere('field', '=', 'value');

        $b->orWhere('other', '=', 'value');

        $this->assertEquals('(|(field=value)(other=value))', $b->getUnescapedQuery());
    }

    public function test_built_or_where_starts_with()
    {
        $b = $this->newBuilder();

        $b->orWhereStartsWith('field', 'value');

        $this->assertEquals('(field=value*)', $b->getUnescapedQuery());
    }

    public function test_built_or_where_ends_with()
    {
        $b = $this->newBuilder();

        $b->orWhereEndsWith('field', 'value');

        $this->assertEquals('(field=*value)', $b->getUnescapedQuery());
    }

    public function test_built_or_where_contains()
    {
        $b = $this->newBuilder();

        $b->orWhereContains('field', 'value');

        $this->assertEquals('(field=*value*)', $b->getUnescapedQuery());
    }

    public function test_built_where_and_or_wheres()
    {
        $b = $this->newBuilder();

        $b->where('field', '=', 'value');

        $b->orWhere('or', '=', 'value');

        $this->assertEquals('(|(field=value)(or=value))', $b->getUnescapedQuery());
    }

    public function test_built_where_has()
    {
        $b = $this->newBuilder();

        $b->whereHas('field');

        $this->assertEquals('(field=*)', $b->getQuery());
    }

    public function test_built_where_not_has()
    {
        $b = $this->newBuilder();

        $b->whereNotHas('field');

        $this->assertEquals('(!(field=*))', $b->getQuery());
    }

    public function test_built_where_not_contains()
    {
        $b = $this->newBuilder();

        $b->whereNotContains('field', 'value');

        $this->assertEquals('(!(field=*value*))', $b->getUnescapedQuery());
    }

    public function test_built_where_in()
    {
        $b = $this->newBuilder();

        $b->whereIn('name', ['john', 'mary', 'sue']);

        $this->assertEquals('(|(name=john)(name=mary)(name=sue))', $b->getUnescapedQuery());
    }

    public function test_built_where_approximately_equals()
    {
        $b = $this->newBuilder();

        $b->whereApproximatelyEquals('field', 'value');

        $this->assertEquals('(field~=value)', $b->getUnescapedQuery());
    }

    public function test_built_or_where_has()
    {
        $b = $this->newBuilder();

        $b->orWhereHas('field');

        $this->assertEquals('(field=*)', $b->getUnescapedQuery());
    }

    public function test_built_or_where_has_multiple()
    {
        $b = $this->newBuilder();

        $b->orWhereHas('one')
            ->orWhereHas('two');

        $this->assertEquals('(|(one=*)(two=*))', $b->getQuery());
    }

    public function test_built_or_where_not_has()
    {
        $b = $this->newBuilder();

        $b->orWhereNotHas('field');

        $this->assertEquals('(!(field=*))', $b->getQuery());
    }

    public function test_built_where_equals()
    {
        $b = $this->newBuilder();

        $b->whereEquals('field', 'value');

        $this->assertEquals('(field=value)', $b->getUnescapedQuery());
    }

    public function test_built_where_not_equals()
    {
        $b = $this->newBuilder();

        $b->whereNotEquals('field', 'value');

        $this->assertEquals('(!(field=value))', $b->getUnescapedQuery());
    }

    public function test_built_or_where_equals()
    {
        $b = $this->newBuilder();

        $b->orWhereEquals('field', 'value');

        // Due to only one 'orWhere' in the current query,
        // a standard filter should be constructed.
        $this->assertEquals('(field=value)', $b->getUnescapedQuery());
    }

    public function test_built_or_where_not_equals()
    {
        $b = $this->newBuilder();

        $b->orWhereNotEquals('field', 'value');

        $this->assertEquals('(!(field=value))', $b->getUnescapedQuery());
    }

    public function test_built_or_where_approximately_equals()
    {
        $b = $this->newBuilder();

        $b->orWhereApproximatelyEquals('field', 'value');

        $this->assertEquals('(field~=value)', $b->getUnescapedQuery());
    }

    public function test_built_raw_filter()
    {
        $b = $this->newBuilder();

        $b->rawFilter('(field=value)');

        $this->assertEquals('(field=value)', $b->getQuery());
    }

    public function test_built_raw_filter_with_wheres()
    {
        $b = $this->newBuilder()
            ->rawFilter('(raw=value)')
            ->where('foo', '=', 'bar')
            ->where('fee', '=', 'lar')
            ->orWhere('baz', '=', 'zax')
            ->orWhere('far', '=', 'zue');

        $this->assertEquals('(&(raw=value)(foo=bar)(fee=lar)(|(baz=zax)(far=zue)))', $b->getUnescapedQuery());
    }

    public function test_built_raw_filter_multiple()
    {
        $b = $this->newBuilder()
            ->rawFilter('(field=value)')
            ->rawFilter('(|(field=value))')
            ->rawFilter('(field=value)');

        $this->assertEquals('(&(field=value)(|(field=value))(field=value))', $b->getQuery());
    }

    public function test_field_is_escaped()
    {
        $b = $this->newBuilder();

        $field = '*^&.:foo()-=';

        $value = 'testing';

        $b->where($field, '=', $value);

        $escapedField = ldap_escape($field, null, 3);

        $escapedValue = ldap_escape($value);

        $this->assertEquals("($escapedField=$escapedValue)", $b->getQuery());
    }

    public function test_builder_dn_is_applied_to_new_instance()
    {
        $b = $this->newBuilder();

        $b->setDn('New DN');

        $newB = $b->newInstance();

        $this->assertEquals('New DN', $newB->getDn());
    }

    public function test_select_args()
    {
        $b = $this->newBuilder();

        $selects = $b->select('attr1', 'attr2', 'attr3')->getSelects();

        $this->assertCount(4, $selects);
        $this->assertEquals('attr1', $selects[0]);
        $this->assertEquals('attr2', $selects[1]);
        $this->assertEquals('attr3', $selects[2]);
    }

    public function test_dynamic_where()
    {
        $b = $this->newBuilder();

        $b->whereCn('test');

        $where = $b->filters['and'][0];

        $this->assertEquals('cn', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\74\65\73\74', $where['value']);
    }

    public function test_dynamic_and_where()
    {
        $b = $this->newBuilder();

        $b->whereCnAndSn('cn', 'sn');

        $wheres = $b->filters['and'];

        $whereCn = $wheres[0];
        $whereSn = $wheres[1];

        $this->assertCount(2, $wheres);

        $this->assertEquals('cn', $whereCn['field']);
        $this->assertEquals('=', $whereCn['operator']);
        $this->assertEquals('\63\6e', $whereCn['value']);

        $this->assertEquals('sn', $whereSn['field']);
        $this->assertEquals('=', $whereSn['operator']);
        $this->assertEquals('\73\6e', $whereSn['value']);
    }

    public function test_dynamic_or_where()
    {
        $b = $this->newBuilder();

        $b->whereCnOrSn('cn', 'sn');

        $wheres = $b->filters['and'];
        $orWheres = $b->filters['or'];

        $whereCn = end($wheres);
        $orWhereSn = end($orWheres);

        $this->assertCount(1, $wheres);
        $this->assertCount(1, $orWheres);

        $this->assertEquals('cn', $whereCn['field']);
        $this->assertEquals('=', $whereCn['operator']);
        $this->assertEquals('\63\6e', $whereCn['value']);

        $this->assertEquals('sn', $orWhereSn['field']);
        $this->assertEquals('=', $orWhereSn['operator']);
        $this->assertEquals('\73\6e', $orWhereSn['value']);
    }

    public function test_selects_are_not_overwritten_with_empty_array()
    {
        $b = $this->newBuilder();

        $b->select(['one', 'two']);

        $b->select([]);

        $this->assertEquals(['one', 'two', 'objectclass'], $b->getSelects());
    }

    public function test_nested_or_filter()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->getUnescapedQuery();

        $this->assertEquals('(|(one=one)(two=two))', $query);
    }

    public function test_nested_and_filter()
    {
        $b = $this->newBuilder();

        $query = $b->andFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->getUnescapedQuery();

        $this->assertEquals('(&(one=one)(two=two))', $query);
    }

    public function test_nested_not_filter()
    {
        $b = $this->newBuilder();

        $query = $b->notFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->getUnescapedQuery();

        $this->assertEquals('(!(one=one)(two=two))', $query);
    }

    public function test_nested_filters()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->andFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->getUnescapedQuery();

        $this->assertEquals('(&(|(one=one)(two=two))(&(one=one)(two=two)))', $query);
    }

    public function test_nested_filters_with_non_nested()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->where([
                'one' => 'one',
                'two' => 'two',
            ]);
        })->andFilter(function ($query) {
            $query->where([
                'three' => 'three',
                'four' => 'four',
            ]);
        })->where([
            'five' => 'five',
            'six' => 'six',
        ])->getUnescapedQuery();

        $this->assertEquals('(&(|(one=one)(two=two))(&(three=three)(four=four))(five=five)(six=six))', $query);
    }

    public function test_nested_builder_is_nested()
    {
        $b = $this->newBuilder();

        $b->andFilter(function ($q) use (&$query) {
            $query = $q;
        });

        $this->assertTrue($query->isNested());
        $this->assertFalse($b->isNested());
    }

    public function test_new_nested_instance_is_nested()
    {
        $b = $this->newBuilder();

        $this->assertTrue($b->newNestedInstance()->isNested());
    }

    public function test_does_not_equal()
    {
        $b = $this->newBuilder();

        $b->where('field', '!', 'value');

        $this->assertEquals('(!(field=value))', $b->getUnescapedQuery());
    }

    public function test_does_not_equal_alias()
    {
        $b = $this->newBuilder();

        $b->where('field', '!=', 'value');

        $this->assertEquals('(!(field=value))', $b->getUnescapedQuery());
    }

    public function test_using_both_equals_and_equals_alias_outputs_same_result()
    {
        $b = $this->newBuilder();

        $b
            ->where('field', '!=', 'value')
            ->where('other', '!', 'value');

        $this->assertEquals('(&(!(field=value))(!(other=value)))', $b->getUnescapedQuery());
    }

    public function test_controls_can_be_added()
    {
        $b = $this->newBuilder();
        $this->assertEmpty($b->controls);

        $b->addControl('foo', true);
        $this->assertEquals(['foo' => ['oid' => 'foo', 'isCritical' => true, 'value' => null]], $b->controls);
    }

    public function test_has_control()
    {
        $b = $this->newBuilder();
        $this->assertFalse($b->hasControl('foo'));

        $b->addControl('foo');
        $this->assertTrue($b->hasControl('foo'));
    }

    public function test_controls_are_not_stacked()
    {
        $b = $this->newBuilder();
        $b->addControl('foo');
        $b->addControl('foo');

        $this->assertCount(1, $b->controls);
        $this->assertEquals(['foo' => ['oid' => 'foo', 'isCritical' => false, 'value' => null]], $b->controls);
    }

    public function test_get()
    {
        $b = $this->newBuilder();

        $result = [
            ['count' => 1, 'cn' => ['Foo']],
            ['count' => 1, 'cn' => ['Bar']],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $this->assertEquals($result, $b->get());
    }

    public function test_first()
    {
        $b = $this->newBuilder();

        $result = [
            ['count' => 1, 'cn' => ['Foo']],
            ['count' => 1, 'cn' => ['Bar']],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $this->assertEquals($result[0], $b->first());
    }

    public function test_sole()
    {
        $b = $this->newBuilder();

        $result = [
            ['count' => 1, 'cn' => ['Foo']],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $this->assertEquals($result[0], $b->sole());
    }

    public function test_sole_throws_objects_not_found_exception_when_nothing_is_returned()
    {
        $b = $this->newBuilder();

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => [],
                'parseResult' => [],
            ]);

        $this->expectException(ObjectsNotFoundException::class);

        $b->sole();
    }

    public function test_sole_throws_multiple_objects_found_exception_when_more_than_one_result_is_returned()
    {
        $b = $this->newBuilder();

        $result = [
            ['count' => 1, 'cn' => ['Foo']],
            ['count' => 1, 'cn' => ['Bar']],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $this->expectException(MultipleObjectsFoundException::class);

        $b->sole();
    }

    public function test_getting_results_sets_ldap_controls()
    {
        $b = $this->newBuilder();

        $ldap = $b->getConnection()->getLdapConnection();

        $ldap->expect([
            'bind' => true,
            'search' => null,
            LdapFake::operation('setOption')->with(LDAP_OPT_PROTOCOL_VERSION, 3)->once(),
            LdapFake::operation('setOption')->with(LDAP_OPT_NETWORK_TIMEOUT, 5)->once(),
            LdapFake::operation('setOption')->with(LDAP_OPT_REFERRALS, 0)->once(),
        ]);

        $b->get();
    }

    public function test_insert_requires_distinguished_name()
    {
        $b = $this->newBuilder();

        $this->expectException(LdapRecordException::class);

        $b->insert('', []);
    }

    public function test_insert_requires_object_classes()
    {
        $b = $this->newBuilder();

        $this->expectException(LdapRecordException::class);

        $b->insert('cn=John Doe', []);
    }

    public function test_find()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $results = [
            'count' => 1,
            [
                'dn' => [$dn],
            ],
        ];

        $b = $this->newBuilder();

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                LdapFake::operation('setOption')->with(LDAP_OPT_PROTOCOL_VERSION, 3)->once(),
                LdapFake::operation('setOption')->with(LDAP_OPT_NETWORK_TIMEOUT, 5)->once(),
                LdapFake::operation('setOption')->with(LDAP_OPT_REFERRALS, 0)->once(),
                LdapFake::operation('read')->once()->with($dn, '(objectclass=*)', ['*'])->andReturn($results),
            ]);

        $this->assertEquals($dn, $b->find($dn)['dn'][0]);
    }

    public function test_insert()
    {
        $b = $this->newBuilder();

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                LdapFake::operation('setOption')->with(LDAP_OPT_PROTOCOL_VERSION, 3)->once(),
                LdapFake::operation('setOption')->with(LDAP_OPT_NETWORK_TIMEOUT, 5)->once(),
                LdapFake::operation('setOption')->with(LDAP_OPT_REFERRALS, 0)->once(),
                LdapFake::operation('add')->with('cn=John Doe', ['objectclass' => ['foo']])->andReturn(true),
            ]);

        $this->assertTrue($b->insert('cn=John Doe', ['objectclass' => ['foo']]));
    }

    public function test_fields_are_escaped()
    {
        $b = $this->newBuilder()->where('(foo)', '=', 'bar');

        $this->assertEquals('(\28foo\29=\62\61\72)', $b->getQuery());
    }

    public function test_pagination()
    {
        $b = $this->newBuilder();

        $result = [
            'count' => 1,
            [
                'count' => 1,
                'objectclass' => ['foo'],
            ],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $this->assertEquals([
            [
                'count' => 1,
                'objectclass' => ['foo'],
            ],
        ], $b->paginate(500));
    }

    public function test_chunk()
    {
        $b = $this->newBuilder();

        $result = [
            'count' => 1,
            [
                'count' => 1,
                'objectclass' => ['foo'],
            ],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $result = $b->chunk(500, function ($results, $page) {
            $this->assertEquals(1, $page);
            $this->assertEquals([
                [
                    'count' => 1,
                    'objectclass' => ['foo'],
                ],
            ], $results);
        });

        $this->assertTrue($result);
    }

    public function test_each()
    {
        $b = $this->newBuilder();

        $result = [
            'count' => 1,
            [
                'count' => 1,
                'objectclass' => ['foo'],
            ],
        ];

        $b->getConnection()
            ->getLdapConnection()
            ->expect([
                'bind' => true,
                'search' => $result,
                'parseResult' => $result,
            ]);

        $result = $b->each(function ($object, $key) {
            $this->assertEquals(0, $key);
            $this->assertEquals([
                'count' => 1,
                'objectclass' => ['foo'],
            ], $object);
        });

        $this->assertTrue($result);
    }

    public function test_setting_dn_with_base_substitutes_with_current_query_base()
    {
        $b = $this->newBuilder()
            ->setBaseDn('dc=base,dc=com')
            ->setDn('ou=users,{base}');

        $this->assertEquals('ou=users,dc=base,dc=com', $b->getDn());
    }

    public function test_setting_base_dn_with_base_substitutes_with_current_query_base()
    {
        $b = $this->newBuilder()
            ->setBaseDn('dc=base,dc=com')
            ->setBaseDn('ou=office,{base}')
            ->setDn('ou=users,{base}');

        $this->assertEquals('ou=users,ou=office,dc=base,dc=com', $b->getDn());
    }

    public function test_setting_dn_with_malformed_base_does_nothing()
    {
        $b = $this->newBuilder()
            ->setBaseDn('dc=base,dc=com')
            ->setDn('ou=users,{bse}');

        $this->assertEquals('ou=users,{bse}', $b->getDn());

        $b = $this->newBuilder()
            ->setBaseDn('dc=base,dc=com')
            ->setDn('ou=users,base}');

        $this->assertEquals('ou=users,base}', $b->getDn());
    }
}
