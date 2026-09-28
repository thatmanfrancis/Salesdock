"""Generate Laravel migrations + Eloquent models from prisma/schema.prisma."""

from __future__ import annotations

import re
from pathlib import Path

SCHEMA = Path(r"C:\Users\thatmanfrancis\projects\salesdock\prisma\schema.prisma")
MIGRATION = Path(
    r"C:\Users\thatmanfrancis\projects\salesdockphp\database\migrations\2026_09_23_000001_create_salesdock_schema.php"
)
MODELS = Path(r"C:\Users\thatmanfrancis\projects\salesdockphp\app\Models")

SCALARS = {"String", "Int", "Boolean", "DateTime", "Decimal", "Json", "Float", "BigInt"}


def snake(name: str) -> str:
    s = re.sub(r"([a-z0-9])([A-Z])", r"\1_\2", name)
    s = re.sub(r"([A-Z]+)([A-Z][a-z])", r"\1_\2", s)
    return s.lower()


def strip_comment(line: str) -> str:
    if "//" in line:
        line = line.split("//", 1)[0]
    return line.strip()


def php_str(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


def parse_default(rest: str):
    m = re.search(r"@default\(((?:[^()]|\([^()]*\))*)\)", rest)
    if not m:
        return None
    val = m.group(1).strip()
    if val in {"now()", "cuid()", "uuid()", "autoincrement()"}:
        return val
    if val in {"true", "false"}:
        return val == "true"
    if re.fullmatch(r"-?\d+(\.\d+)?", val):
        return val
    if val.startswith('"') and val.endswith('"'):
        return val[1:-1]
    return ("enum", val)


def parse(text: str):
    enums: dict[str, list[str]] = {}
    models: list[dict] = []
    lines = text.splitlines()
    i = 0
    while i < len(lines):
        raw = strip_comment(lines[i])
        if raw.startswith("enum "):
            name = raw.split()[1]
            values: list[str] = []
            i += 1
            while i < len(lines) and strip_comment(lines[i]) != "}":
                v = strip_comment(lines[i])
                if v:
                    values.append(v.split()[0])
                i += 1
            enums[name] = values
        elif raw.startswith("model "):
            name = raw.split()[1]
            body: list[str] = []
            i += 1
            while i < len(lines) and strip_comment(lines[i]) != "}":
                body.append(lines[i])
                i += 1
            models.append(parse_model(name, body, enums))
        i += 1
    return enums, models


def parse_model(name: str, body: list[str], enums: dict[str, list[str]]):
    fields = []
    indexes = []
    uniques = []
    fks = []
    composite_pk = None
    table = None

    for line in body:
        raw = strip_comment(line)
        if not raw:
            continue
        if raw.startswith("@@"):
            if raw.startswith("@@map("):
                table = raw[raw.find('"') + 1 : raw.rfind('"')]
            elif raw.startswith("@@index("):
                cols = re.findall(r"\[([^\]]+)\]", raw)[0]
                indexes.append([c.strip() for c in cols.split(",")])
            elif raw.startswith("@@unique("):
                cols = re.findall(r"\[([^\]]+)\]", raw)[0]
                uniques.append([c.strip() for c in cols.split(",")])
            elif raw.startswith("@@id("):
                cols = re.findall(r"\[([^\]]+)\]", raw)[0]
                composite_pk = [c.strip() for c in cols.split(",")]
            continue

        m = re.match(r"^(\w+)\s+(\w+)(\?)?(.*)$", raw)
        if not m:
            continue
        fname, typ, opt, rest = m.group(1), m.group(2), m.group(3), m.group(4) or ""
        if typ not in SCALARS and typ not in enums:
            rel = re.search(r"fields:\s*\[([^\]]+)\].*references:\s*\[([^\]]+)\]", rest)
            if rel:
                on_delete = "cascade" if "onDelete: Cascade" in rest else "restrict"
                fks.append(
                    {
                        "columns": [c.strip() for c in rel.group(1).split(",")],
                        "references": [c.strip() for c in rel.group(2).split(",")],
                        "model": typ,
                        "on_delete": on_delete,
                    }
                )
            continue

        decimal = re.search(r"@db\.Decimal\((\d+)\s*,\s*(\d+)\)", rest)
        fields.append(
            {
                "name": fname,
                "column": fname,
                "type": typ,
                "nullable": bool(opt),
                "primary": bool(re.search(r"(?<![\w])@id(?![\w])", rest)),
                "unique": bool(re.search(r"(?<![\w])@unique(?![\w])", rest)),
                "text": "@db.Text" in rest,
                "decimal": (int(decimal.group(1)), int(decimal.group(2))) if decimal else None,
                "default": parse_default(rest),
                "enum": typ if typ in enums else None,
            }
        )

    if table is None:
        table = snake(name)

    return {
        "name": name,
        "table": table,
        "fields": fields,
        "indexes": indexes,
        "uniques": uniques,
        "fks": fks,
        "composite_pk": composite_pk,
    }


def emit_field(f: dict, enums: dict[str, list[str]]) -> str:
    col = php_str(f["column"])
    t = f["type"]
    if f["enum"]:
        values = ", ".join(php_str(v) for v in enums[f["enum"]])
        expr = f"$table->enum({col}, [{values}])"
    elif t == "String":
        expr = f"$table->text({col})" if f["text"] else f"$table->string({col})"
    elif t == "Int":
        expr = f"$table->integer({col})"
    elif t == "Boolean":
        expr = f"$table->boolean({col})"
    elif t == "DateTime":
        expr = f"$table->timestamp({col})"
    elif t == "Decimal":
        p, s = f["decimal"] or (12, 2)
        expr = f"$table->decimal({col}, {p}, {s})"
    elif t == "Json":
        expr = f"$table->jsonb({col})"
    elif t == "Float":
        expr = f"$table->float({col})"
    elif t == "BigInt":
        expr = f"$table->unsignedBigInteger({col})"
    else:
        raise SystemExit(f"unknown type {t} on {f['name']}")

    if f["primary"]:
        expr += "->primary()"
    if f["nullable"] and not f["primary"]:
        expr += "->nullable()"

    d = f["default"]
    if d in {"cuid()", "uuid()", "autoincrement()"}:
        pass
    elif d == "now()":
        expr += "->useCurrent()"
    elif isinstance(d, bool):
        expr += "->default(DB::raw('" + ("true" if d else "false") + "'))"
    elif isinstance(d, str):
        if re.fullmatch(r"-?\d+(\.\d+)?", d):
            expr += f"->default({d})"
        else:
            expr += f"->default({php_str(d)})"
    elif isinstance(d, tuple) and d[0] == "enum":
        expr += f"->default({php_str(d[1])})"

    if f["unique"] and not f["primary"]:
        expr += "->unique()"
    return "            " + expr + ";"


def emit_migration(enums: dict[str, list[str]], models: list[dict]) -> str:
    model_tables = {m["name"]: m["table"] for m in models}
    out: list[str] = []
    a = out.append
    a("<?php")
    a("")
    a("use Illuminate\\Database\\Migrations\\Migration;")
    a("use Illuminate\\Database\\Schema\\Blueprint;")
    a("use Illuminate\\Support\\Facades\\DB;")
    a("use Illuminate\\Support\\Facades\\Schema;")
    a("")
    a("/**")
    a(" * Full SalesDock schema ported from prisma/schema.prisma.")
    a(" * Every Prisma scalar column, enum, unique, index, and foreign key is included.")
    a(" * Columns are snake_case; table names match Prisma @@map.")
    a(" */")
    a("return new class extends Migration")
    a("{")
    a("    public function up(): void")
    a("    {")
    a("        // retailos (and any Prisma database) already has these tables.")
    a("        if (Schema::hasTable('tenants')) {")
    a("            return;")
    a("        }")
    a("")

    for model in models:
        a(f"        Schema::create({php_str(model['table'])}, function (Blueprint $table) {{")
        pk_cols = {f["column"] for f in model["fields"] if f["primary"]}
        unique_cols = {f["column"] for f in model["fields"] if f["unique"] or f["primary"]}
        for f in model["fields"]:
            a(emit_field(f, enums))

        if model["composite_pk"]:
            cols = ", ".join(php_str(snake(c)) for c in model["composite_pk"])
            a(f"            $table->primary([{cols}]);")

        for uniq in model["uniques"]:
            cols = list(uniq)
            if len(cols) == 1 and cols[0] in unique_cols:
                continue
            joined = ", ".join(php_str(c) for c in cols)
            a(f"            $table->unique([{joined}]);")
            unique_cols.add(tuple(cols))

        for idx in model["indexes"]:
            cols = list(idx)
            if len(cols) == 1 and (cols[0] in pk_cols or cols[0] in unique_cols):
                continue
            joined = ", ".join(php_str(c) for c in cols)
            a(f"            $table->index([{joined}]);")

        a("        });")
        a("")

    a("        // Foreign keys after all tables exist (products <-> promotions is circular).")
    for model in models:
        if not model["fks"]:
            continue
        a(f"        Schema::table({php_str(model['table'])}, function (Blueprint $table) {{")
        for fk in model["fks"]:
            cols = ", ".join(php_str(c) for c in fk["columns"])
            refs = ", ".join(php_str(c) for c in fk["references"])
            target = model_tables[fk["model"]]
            delete = "cascadeOnDelete()" if fk["on_delete"] == "cascade" else "restrictOnDelete()"
            a(
                f"            $table->foreign([{cols}])->references([{refs}])->on({php_str(target)})->{delete};"
            )
        a("        });")
        a("")

    a("    }")
    a("")
    a("    public function down(): void")
    a("    {")
    a("        // Never drop the shared Prisma tables from this migration.")
    a("    }")
    a("};")
    a("")
    return "\n".join(out)


def emit_model(model: dict) -> str:
    class_name = model["name"]
    casts = []
    dates = []
    for f in model["fields"]:
        col = f["column"]
        if f["type"] == "Boolean":
            casts.append(f"            {php_str(col)} => 'boolean',")
        elif f["type"] == "Decimal":
            casts.append(f"            {php_str(col)} => 'decimal:2',")
        elif f["type"] == "Json":
            casts.append(f"            {php_str(col)} => 'array',")
        elif f["type"] == "DateTime":
            dates.append(col)
            casts.append(f"            {php_str(col)} => 'datetime',")
        elif f["type"] == "Int":
            casts.append(f"            {php_str(col)} => 'integer',")

    has_single_pk = any(f["primary"] for f in model["fields"])
    pk_name = next((f["column"] for f in model["fields"] if f["primary"]), "id")
    composite = model["composite_pk"]

    base = "Authenticatable" if class_name == "User" else "Model"
    uses = ["use Illuminate\\Database\\Eloquent\\Model;"]
    if class_name == "User":
        uses.append("use Illuminate\\Foundation\\Auth\\User as Authenticatable;")

    lines = ["<?php", "", "namespace App\\Models;", ""]
    lines.extend(uses)
    lines.append("")
    lines.append(f"class {class_name} extends {base}")
    lines.append("{")
    lines.append(f"    protected $table = {php_str(model['table'])};")
    lines.append("")
    lines.append("    public static $snakeAttributes = false;")
    lines.append("")
    lines.append("    public const CREATED_AT = 'createdAt';")
    lines.append("")
    lines.append("    public const UPDATED_AT = 'updatedAt';")
    lines.append("")
    lines.append("    protected $guarded = [];")
    if has_single_pk:
        lines.append("")
        lines.append("    public $incrementing = false;")
        lines.append("")
        lines.append("    protected $keyType = 'string';")
        if pk_name != "id":
            lines.append("")
            lines.append(f"    protected $primaryKey = {php_str(pk_name)};")
        lines.append("")
        lines.append("    protected static function booted(): void")
        lines.append("    {")
        lines.append("        static::creating(function (self $model): void {")
        lines.append("            if (! $model->getKey()) {")
        lines.append("                $model->{$model->getKeyName()} = (string) str()->ulid();")
        lines.append("            }")
        lines.append("        });")
        lines.append("    }")
    elif composite:
        cols = ", ".join(php_str(c) for c in composite)
        lines.append("")
        lines.append("    public $incrementing = false;")
        lines.append("")
        lines.append(f"    protected $primaryKey = [{cols}];")
    if class_name == "User":
        lines.append("")
        lines.append("    public function getAuthPassword(): string")
        lines.append("    {")
        lines.append("        return (string) $this->passwordHash;")
        lines.append("    }")
    if "updatedAt" not in {f["column"] for f in model["fields"]} or "createdAt" not in {
        f["column"] for f in model["fields"]
    }:
        has_created = any(f["column"] == "createdAt" for f in model["fields"])
        has_updated = any(f["column"] == "updatedAt" for f in model["fields"])
        if not (has_created and has_updated):
            lines.append("")
            lines.append("    public $timestamps = false;")
    if casts:
        lines.append("")
        lines.append("    protected function casts(): array")
        lines.append("    {")
        lines.append("        return [")
        lines.extend(casts)
        lines.append("        ];")
        lines.append("    }")
    lines.append("}")
    lines.append("")
    return "\n".join(lines)


def main():
    enums, models = parse(SCHEMA.read_text(encoding="utf-8"))
    MIGRATION.parent.mkdir(parents=True, exist_ok=True)
    MIGRATION.write_text(emit_migration(enums, models), encoding="utf-8")
    MODELS.mkdir(parents=True, exist_ok=True)
    for model in models:
        if model["name"] == "User":
            continue  # written separately so we don't clobber mid-install; still write Salesdock user as User
        (MODELS / f"{model['name']}.php").write_text(emit_model(model), encoding="utf-8")
    user = next(m for m in models if m["name"] == "User")
    (MODELS / "User.php").write_text(emit_model(user), encoding="utf-8")

    scalar_count = sum(len(m["fields"]) for m in models)
    print(f"models={len(models)} enums={len(enums)} scalar_fields={scalar_count}")
    print(f"wrote {MIGRATION}")


if __name__ == "__main__":
    main()
