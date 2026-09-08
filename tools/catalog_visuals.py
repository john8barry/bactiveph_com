"""Offline image-assignment planning only. No network or mutation executor."""
import argparse
import json
import re
import sys

TARGET = "https://bactiveph.com"


class ValidationError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise ValidationError(message)


def object_keys(value, keys):
    require(isinstance(value, dict) and set(value) == set(keys),
            "Object has missing or unowned fields")


def identifier(value):
    require(type(value) is int and value > 0, "IDs must be positive integers")
    return value


def image_id(value):
    object_keys(value, {"id"})
    return identifier(value["id"])


def image_ids(value):
    require(isinstance(value, list), "Images must be an ordered list")
    ids = [image_id(image) for image in value]
    require(len(ids) == len(set(ids)), "Duplicate attachment in gallery")
    return ids


def context(target, release_id):
    require(target == TARGET, "Unexpected production target")
    require(isinstance(release_id, str) and
            re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._-]{0,99}", release_id),
            "Invalid release ID")


def index_products(products, allow_context=False):
    """Strict owned-field projection; live context is read-only and ignored."""
    require(isinstance(products, list) and products, "Expected nonempty product list")
    result = {}
    seen_ids = set()
    for product in products:
        require(isinstance(product, dict), "Product must be an object")
        if not allow_context:
            object_keys(product, {"id", "images", "variations"})
        require({"id", "images", "variations"} <= set(product), "Missing product fields")
        pid = identifier(product["id"])
        require(pid not in seen_ids, "Duplicate object ID")
        seen_ids.add(pid)
        result[(pid, pid, "images")] = image_ids(product["images"])
        require(isinstance(product["variations"], list), "Variations must be a list of objects")
        for variation in product["variations"]:
            require(isinstance(variation, dict), "Variation must be an object")
            if not allow_context:
                object_keys(variation, {"id", "image"})
            require({"id", "image"} <= set(variation), "Missing variation fields")
            vid = identifier(variation["id"])
            require(vid not in seen_ids, "Duplicate object ID")
            seen_ids.add(vid)
            result[(pid, vid, "image")] = image_id(variation["image"])
    return result


def build_plan(before, proposed, *, target, release_id):
    context(target, release_id)
    old, new = index_products(before), index_products(proposed)
    require(old.keys() == new.keys(), "Product/variation ownership or IDs changed")
    operations = []
    for (pid, oid, field), previous in old.items():
        following = new[(pid, oid, field)]
        if previous != following:
            operations.append({"product_id": pid, "object_id": oid,
                               "field": field, "before": previous, "after": following})
    return {"version": 1, "mode": "offline-preview", "target": target,
            "release_id": release_id, "operations": operations}


def validate_plan(plan, *, target, release_id):
    context(target, release_id)
    object_keys(plan, {"version", "mode", "target", "release_id", "operations"})
    require(type(plan["version"]) is int and plan["version"] == 1 and
            plan["mode"] == "offline-preview", "Unsupported plan")
    require(plan["target"] == target and plan["release_id"] == release_id,
            "Plan target/release mismatch")
    require(isinstance(plan["operations"], list), "Operations must be a list")
    seen, owners = set(), {}
    for operation in plan["operations"]:
        object_keys(operation, {"product_id", "object_id", "field", "before", "after"})
        pid, oid = identifier(operation["product_id"]), identifier(operation["object_id"])
        field = operation["field"]
        require(field in ("images", "image"), "Unowned field")
        require((field == "images") == (pid == oid), "Invalid product/variation ownership")
        require(oid not in owners or owners[oid] == pid, "Conflicting object ownership")
        owners[oid] = pid
        require(oid not in seen, "Duplicate object/field operation")
        seen.add(oid)
        for side in ("before", "after"):
            value = operation[side]
            if field == "images":
                require(isinstance(value, list), "Gallery IDs must be a list")
                ids = [identifier(item) for item in value]
                require(len(ids) == len(set(ids)), "Duplicate attachment in gallery")
            else:
                identifier(value)
        require(operation["before"] != operation["after"], "No-op operation")


def reverse_plan(plan, current, *, target, release_id):
    """Validate the entire batch before returning any reverse operations.

    Does not mutate current or plan. Unrelated commerce fields are never copied
    into output. Callers must re-read/revalidate at execution time in future.
    """
    validate_plan(plan, target=target, release_id=release_id)
    actual = index_products(current, allow_context=True)
    reverse, restored = [], []
    for operation in plan["operations"]:
        key = (operation["product_id"], operation["object_id"], operation["field"])
        require(key in actual, "Missing object or changed product ownership")
        value = actual[key]
        if value == operation["before"]:
            restored.append({"product_id": key[0], "object_id": key[1], "field": key[2]})
        else:
            require(value == operation["after"], "Later-writer image conflict; entire batch rejected")
            reverse.append({**operation, "before": operation["after"], "after": operation["before"]})
    return {"version": 1, "mode": "offline-preview", "target": target,
            "release_id": release_id, "operations": reverse,
            "already_restored": restored}


def unique_keys(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, "Duplicate JSON field")
        result[key] = value
    return result


def read_json(path):
    with open(path, encoding="utf-8") as source:
        return json.load(source, object_pairs_hook=unique_keys)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--target", required=True, choices=[TARGET])
    parser.add_argument("--release-id", required=True)
    sub = parser.add_subparsers(dest="command", required=True)
    build = sub.add_parser("plan")
    build.add_argument("--before", required=True)
    build.add_argument("--proposed", required=True)
    reverse = sub.add_parser("reverse-preview")
    reverse.add_argument("--plan", required=True)
    reverse.add_argument("--current", required=True)
    args = parser.parse_args(argv)
    try:
        kwargs = {"target": args.target, "release_id": args.release_id}
        if args.command == "plan":
            result = build_plan(read_json(args.before), read_json(args.proposed), **kwargs)
        else:
            result = reverse_plan(read_json(args.plan), read_json(args.current), **kwargs)
        print(json.dumps(result, indent=2))
        return 0
    except (ValidationError, OSError, ValueError, TypeError, KeyError):
        # Do not echo file contents, paths, or arbitrary input values.
        print("Invalid input or conflicting state; no operations executed.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
