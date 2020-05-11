<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

/** @noinspection PhpUnused */

/**
 * Holds default Postgres type OIDs
 */
final class PgTypes
{
    /**
     * OID of the `bool` type.
     */
    public const BOOL = 16;
    /**
     * OID of the `bytea` type.
     */
    public const BYTEA = 17;
    /**
     * OID of the `char` type.
     */
    public const CHAR = 18;
    /**
     * OID of the `name` type.
     */
    public const NAME = 19;
    /**
     * OID of the `int8` type.
     */
    public const INT8 = 20;
    /**
     * OID of the `int2` type.
     */
    public const INT2 = 21;
    /**
     * OID of the `int2vector` type.
     */
    public const INT2VECTOR = 22;
    /**
     * OID of the `int4` type.
     */
    public const INT4 = 23;
    /**
     * OID of the `regproc` type.
     */
    public const REGPROC = 24;
    /**
     * OID of the `text` type.
     */
    public const TEXT = 25;
    /**
     * OID of the `oid` type.
     */
    public const OID = 26;
    /**
     * OID of the `tid` type.
     */
    public const TID = 27;
    /**
     * OID of the `xid` type.
     */
    public const XID = 28;
    /**
     * OID of the `cid` type.
     */
    public const CID = 29;
    /**
     * OID of the `oidvector` type.
     */
    public const OIDVECTOR = 30;
    /**
     * OID of the `pg_type` type.
     */
    public const PG_TYPE = 71;
    /**
     * OID of the `pg_attribute` type.
     */
    public const PG_ATTRIBUTE = 75;
    /**
     * OID of the `pg_proc` type.
     */
    public const PG_PROC = 81;
    /**
     * OID of the `pg_class` type.
     */
    public const PG_CLASS = 83;
    /**
     * OID of the `json` type.
     */
    public const JSON = 114;
    /**
     * OID of the `xml` type.
     */
    public const XML = 142;
    /**
     * OID of the `xmlarray` type.
     */
    public const XMLARRAY = 143;
    /**
     * OID of the `jsonarray` type.
     */
    public const JSONARRAY = 199;
    /**
     * OID of the `pg_node_tree` type.
     */
    public const PG_NODE_TREE = 194;
    /**
     * OID of the `smgr` type.
     */
    public const SMGR = 210;
    /**
     * OID of the `point` type.
     */
    public const POINT = 600;
    /**
     * OID of the `lseg` type.
     */
    public const LSEG = 601;
    /**
     * OID of the `path` type.
     */
    public const PATH = 602;
    /**
     * OID of the `box` type.
     */
    public const BOX = 603;
    /**
     * OID of the `polygon` type.
     */
    public const POLYGON = 604;
    /**
     * OID of the `line` type.
     */
    public const LINE = 628;
    /**
     * OID of the `linearray` type.
     */
    public const LINEARRAY = 629;
    /**
     * OID of the `float4` type.
     */
    public const FLOAT4 = 700;
    /**
     * OID of the `float8` type.
     */
    public const FLOAT8 = 701;
    /**
     * OID of the `abstime` type.
     */
    public const ABSTIME = 702;
    /**
     * OID of the `reltime` type.
     */
    public const RELTIME = 703;
    /**
     * OID of the `tinterval` type.
     */
    public const TINTERVAL = 704;
    /**
     * OID of the `unknown` type.
     */
    public const UNKNOWN = 705;
    /**
     * OID of the `circle` type.
     */
    public const CIRCLE = 718;
    /**
     * OID of the `circlearray` type.
     */
    public const CIRCLEARRAY = 719;
    /**
     * OID of the `money` type.
     */
    public const MONEY = 790;
    /**
     * OID of the `moneyarray` type.
     */
    public const MONEYARRAY = 791;
    /**
     * OID of the `macaddr` type.
     */
    public const MACADDR = 829;
    /**
     * OID of the `inet` type.
     */
    public const INET = 869;
    /**
     * OID of the `cidr` type.
     */
    public const CIDR = 650;
    /**
     * OID of the `boolarray` type.
     */
    public const BOOLARRAY = 1000;
    /**
     * OID of the `byteaarray` type.
     */
    public const BYTEAARRAY = 1001;
    /**
     * OID of the `chararray` type.
     */
    public const CHARARRAY = 1002;
    /**
     * OID of the `namearray` type.
     */
    public const NAMEARRAY = 1003;
    /**
     * OID of the `int2array` type.
     */
    public const INT2ARRAY = 1005;
    /**
     * OID of the `int2vectorarray` type.
     */
    public const INT2VECTORARRAY = 1006;
    /**
     * OID of the `int4array` type.
     */
    public const INT4ARRAY = 1007;
    /**
     * OID of the `regprocarray` type.
     */
    public const REGPROCARRAY = 1008;
    /**
     * OID of the `textarray` type.
     */
    public const TEXTARRAY = 1009;
    /**
     * OID of the `oidarray` type.
     */
    public const OIDARRAY = 1028;
    /**
     * OID of the `tidarray` type.
     */
    public const TIDARRAY = 1010;
    /**
     * OID of the `xidarray` type.
     */
    public const XIDARRAY = 1011;
    /**
     * OID of the `cidarray` type.
     */
    public const CIDARRAY = 1012;
    /**
     * OID of the `oidvectorarray` type.
     */
    public const OIDVECTORARRAY = 1013;
    /**
     * OID of the `bpchararray` type.
     */
    public const BPCHARARRAY = 1014;
    /**
     * OID of the `varchararray` type.
     */
    public const VARCHARARRAY = 1015;
    /**
     * OID of the `int8array` type.
     */
    public const INT8ARRAY = 1016;
    /**
     * OID of the `pointarray` type.
     */
    public const POINTARRAY = 1017;
    /**
     * OID of the `lsegarray` type.
     */
    public const LSEGARRAY = 1018;
    /**
     * OID of the `patharray` type.
     */
    public const PATHARRAY = 1019;
    /**
     * OID of the `boxarray` type.
     */
    public const BOXARRAY = 1020;
    /**
     * OID of the `float4array` type.
     */
    public const FLOAT4ARRAY = 1021;
    /**
     * OID of the `float8array` type.
     */
    public const FLOAT8ARRAY = 1022;
    /**
     * OID of the `abstimearray` type.
     */
    public const ABSTIMEARRAY = 1023;
    /**
     * OID of the `reltimearray` type.
     */
    public const RELTIMEARRAY = 1024;
    /**
     * OID of the `tintervalarray` type.
     */
    public const TINTERVALARRAY = 1025;
    /**
     * OID of the `polygonarray` type.
     */
    public const POLYGONARRAY = 1027;
    /**
     * OID of the `aclitem` type.
     */
    public const ACLITEM = 1033;
    /**
     * OID of the `aclitemarray` type.
     */
    public const ACLITEMARRAY = 1034;
    /**
     * OID of the `macaddrarray` type.
     */
    public const MACADDRARRAY = 1040;
    /**
     * OID of the `inetarray` type.
     */
    public const INETARRAY = 1041;
    /**
     * OID of the `cidrarray` type.
     */
    public const CIDRARRAY = 651;
    /**
     * OID of the `cstringarray` type.
     */
    public const CSTRINGARRAY = 1263;
    /**
     * OID of the `bpchar` type.
     */
    public const BPCHAR = 1042;
    /**
     * OID of the `varchar` type.
     */
    public const VARCHAR = 1043;
    /**
     * OID of the `date` type.
     */
    public const DATE = 1082;
    /**
     * OID of the `time` type.
     */
    public const TIME = 1083;
    /**
     * OID of the `timestamp` type.
     */
    public const TIMESTAMP = 1114;
    /**
     * OID of the `timestamparray` type.
     */
    public const TIMESTAMPARRAY = 1115;
    /**
     * OID of the `datearray` type.
     */
    public const DATEARRAY = 1182;
    /**
     * OID of the `timearray` type.
     */
    public const TIMEARRAY = 1183;
    /**
     * OID of the `timestamptz` type.
     */
    public const TIMESTAMPTZ = 1184;
    /**
     * OID of the `timestamptzarray` type.
     */
    public const TIMESTAMPTZARRAY = 1185;
    /**
     * OID of the `interval` type.
     */
    public const INTERVAL = 1186;
    /**
     * OID of the `intervalarray` type.
     */
    public const INTERVALARRAY = 1187;
    /**
     * OID of the `numericarray` type.
     */
    public const NUMERICARRAY = 1231;
    /**
     * OID of the `timetz` type.
     */
    public const TIMETZ = 1266;
    /**
     * OID of the `timetzarray` type.
     */
    public const TIMETZARRAY = 1270;
    /**
     * OID of the `bit` type.
     */
    public const BIT = 1560;
    /**
     * OID of the `bitarray` type.
     */
    public const BITARRAY = 1561;
    /**
     * OID of the `varbit` type.
     */
    public const VARBIT = 1562;
    /**
     * OID of the `varbitarray` type.
     */
    public const VARBITARRAY = 1563;
    /**
     * OID of the `numeric` type.
     */
    public const NUMERIC = 1700;
    /**
     * OID of the `refcursor` type.
     */
    public const REFCURSOR = 1790;
    /**
     * OID of the `refcursorarray` type.
     */
    public const REFCURSORARRAY = 2201;
    /**
     * OID of the `regprocedure` type.
     */
    public const REGPROCEDURE = 2202;
    /**
     * OID of the `regoper` type.
     */
    public const REGOPER = 2203;
    /**
     * OID of the `regoperator` type.
     */
    public const REGOPERATOR = 2204;
    /**
     * OID of the `regclass` type.
     */
    public const REGCLASS = 2205;
    /**
     * OID of the `regtype` type.
     */
    public const REGTYPE = 2206;
    /**
     * OID of the `regprocedurearray` type.
     */
    public const REGPROCEDUREARRAY = 2207;
    /**
     * OID of the `regoperarray` type.
     */
    public const REGOPERARRAY = 2208;
    /**
     * OID of the `regoperatorarray` type.
     */
    public const REGOPERATORARRAY = 2209;
    /**
     * OID of the `regclassarray` type.
     */
    public const REGCLASSARRAY = 2210;
    /**
     * OID of the `regtypearray` type.
     */
    public const REGTYPEARRAY = 2211;
    /**
     * OID of the `uuid` type.
     */
    public const UUID = 2950;
    /**
     * OID of the `uuidarray` type.
     */
    public const UUIDARRAY = 2951;
    /**
     * OID of the `tsvector` type.
     */
    public const TSVECTOR = 3614;
    /**
     * OID of the `gtsvector` type.
     */
    public const GTSVECTOR = 3642;
    /**
     * OID of the `tsquery` type.
     */
    public const TSQUERY = 3615;
    /**
     * OID of the `regconfig` type.
     */
    public const REGCONFIG = 3734;
    /**
     * OID of the `regdictionary` type.
     */
    public const REGDICTIONARY = 3769;
    /**
     * OID of the `tsvectorarray` type.
     */
    public const TSVECTORARRAY = 3643;
    /**
     * OID of the `gtsvectorarray` type.
     */
    public const GTSVECTORARRAY = 3644;
    /**
     * OID of the `tsqueryarray` type.
     */
    public const TSQUERYARRAY = 3645;
    /**
     * OID of the `regconfigarray` type.
     */
    public const REGCONFIGARRAY = 3735;
    /**
     * OID of the `regdictionaryarray` type.
     */
    public const REGDICTIONARYARRAY = 3770;
    /**
     * OID of the `txid_snapshot` type.
     */
    public const TXID_SNAPSHOT = 2970;
    /**
     * OID of the `txid_snapshotarray` type.
     */
    public const TXID_SNAPSHOTARRAY = 2949;
    /**
     * OID of the `int4range` type.
     */
    public const INT4RANGE = 3904;
    /**
     * OID of the `int4rangearray` type.
     */
    public const INT4RANGEARRAY = 3905;
    /**
     * OID of the `numrange` type.
     */
    public const NUMRANGE = 3906;
    /**
     * OID of the `numrangearray` type.
     */
    public const NUMRANGEARRAY = 3907;
    /**
     * OID of the `tsrange` type.
     */
    public const TSRANGE = 3908;
    /**
     * OID of the `tsrangearray` type.
     */
    public const TSRANGEARRAY = 3909;
    /**
     * OID of the `tstzrange` type.
     */
    public const TSTZRANGE = 3910;
    /**
     * OID of the `tstzrangearray` type.
     */
    public const TSTZRANGEARRAY = 3911;
    /**
     * OID of the `daterange` type.
     */
    public const DATERANGE = 3912;
    /**
     * OID of the `daterangearray` type.
     */
    public const DATERANGEARRAY = 3913;
    /**
     * OID of the `int8range` type.
     */
    public const INT8RANGE = 3926;
    /**
     * OID of the `int8rangearray` type.
     */
    public const INT8RANGEARRAY = 3927;
    /**
     * OID of the `record` type.
     */
    public const RECORD = 2249;
    /**
     * OID of the `recordarray` type.
     */
    public const RECORDARRAY = 2287;
    /**
     * OID of the `cstring` type.
     */
    public const CSTRING = 2275;
    /**
     * OID of the `any` type.
     */
    public const ANY = 2276;
    /**
     * OID of the `anyarray` type.
     */
    public const ANYARRAY = 2277;
    /**
     * OID of the `void` type.
     */
    public const VOID = 2278;
    /**
     * OID of the `trigger` type.
     */
    public const TRIGGER = 2279;
    /**
     * OID of the `event_trigger` type.
     */
    public const EVENT_TRIGGER = 3838;
    /**
     * OID of the `language_handler` type.
     */
    public const LANGUAGE_HANDLER = 2280;
    /**
     * OID of the `internal` type.
     */
    public const INTERNAL = 2281;
    /**
     * OID of the `opaque` type.
     */
    public const OPAQUE = 2282;
    /**
     * OID of the `anyelement` type.
     */
    public const ANYELEMENT = 2283;
    /**
     * OID of the `anynonarray` type.
     */
    public const ANYNONARRAY = 2776;
    /**
     * OID of the `anyenum` type.
     */
    public const ANYENUM = 3500;
    /**
     * OID of the `fdw_handler` type.
     */
    public const FDW_HANDLER = 3115;
    /**
     * OID of the `anyrange` type.
     */
    public const ANYRANGE = 3831;
    /**
     * OID of the `jsonb` type.
     */
    public const JSONB = 3802;
    /**
     * OID of the `jsonbarray` type.
     */
    public const JSONBARRAY = 3807;
}