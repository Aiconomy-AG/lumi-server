#!/usr/bin/env python3
"""Build the canonical OpenAPI document from Laravel routes and maintained specs."""

from __future__ import annotations

import copy
import json
import re
import subprocess
from pathlib import Path
from typing import Any

import yaml

ROOT = Path(__file__).resolve().parents[2]
API_DIR = ROOT / "docs" / "api"
SOURCE_SPECS = [API_DIR / "v1" / "Shop.yaml", API_DIR / "v1" / "Workspace.yaml"]
OUTPUT = API_DIR / "openapi.yaml"
ROUTES_OUTPUT = API_DIR / "routes.md"
HTTP_METHODS = {"get", "post", "put", "patch", "delete", "options"}
NO_BODY_OPERATIONS = {
    ("post", "/admin/returns/{returnRequestId}/approve"),
    ("post", "/admin/returns/{returnRequestId}/received"),
    ("post", "/admin/returns/{returnRequestId}/refunded"),
}


def documented_request_schema(method: str, path: str) -> tuple[dict[str, Any], list[str]] | None:
    key = (method, path)
    integer_id = {"type": "integer", "minimum": 1}
    quantity = {"type": "integer", "minimum": 1}
    schemas: dict[tuple[str, str], tuple[dict[str, Any], list[str]]] = {
        ("post", "/admin/returns/{returnRequestId}/reject"): (
            {"type": "object", "properties": {"notes": {"type": "string", "nullable": True, "maxLength": 2000}}}, []
        ),
        ("post", "/shopify/proxy/cart/items"): (
            {"type": "object", "properties": {"product_variant_id": integer_id, "quantity": quantity}},
            ["product_variant_id", "quantity"],
        ),
        ("put", "/shopify/proxy/cart/items/{productVariantId}"): (
            {"type": "object", "properties": {"quantity": quantity}}, ["quantity"]
        ),
        ("post", "/shopify/customer-account/returns/lookup"): (
            {"type": "object", "properties": {"shopify_order_id": {"type": "string", "maxLength": 255}}},
            ["shopify_order_id"],
        ),
        ("post", "/shopify/customer-account/returns/by-order"): (
            {"type": "object", "properties": {"shopify_order_id": {"type": "string", "maxLength": 255}}},
            ["shopify_order_id"],
        ),
        ("put", "/workspace/conversations/{conversationId}"): (
            {
                "type": "object",
                "properties": {
                    "name": {"type": "string", "nullable": True, "maxLength": 255},
                    "add_participants_employee_ids": {"type": "array", "items": integer_id},
                    "remove_participants_employee_ids": {"type": "array", "items": integer_id},
                },
            },
            [],
        ),
        ("patch", "/workspace/returns/{returnRequestId}"): (
            {
                "type": "object",
                "properties": {
                    "status": {"type": "string", "enum": ["requested", "approved", "rejected", "received", "refunded"]},
                    "notes": {"type": "string", "nullable": True, "maxLength": 2000},
                },
            },
            ["status"],
        ),
    }
    common_return_item = {
        "type": "object",
        "properties": {
            "shopify_line_item_id": {"type": "string", "nullable": True, "maxLength": 255},
            "shopify_product_id": {"type": "string", "nullable": True, "maxLength": 255},
            "title": {"type": "string", "nullable": True, "maxLength": 255},
            "sku": {"type": "string", "nullable": True, "maxLength": 255},
            "unit_price": {"type": "number", "nullable": True, "minimum": 0},
            "quantity": quantity,
        },
        "required": ["quantity"],
    }
    schemas[("post", "/shopify/customer-account/returns")] = (
        {
            "type": "object",
            "properties": {
                "order_id": {"type": "string", "maxLength": 255},
                "shopify_order_id": {"type": "string", "maxLength": 255},
                "email": {"type": "string", "nullable": True, "format": "email", "maxLength": 255},
                "items": {"type": "array", "minItems": 1, "items": common_return_item},
                "reason": {"type": "string", "maxLength": 255},
                "notes": {"type": "string", "nullable": True, "maxLength": 2000},
            },
        },
        ["order_id", "shopify_order_id", "items", "reason"],
    )
    return schemas.get(key)


def load_routes() -> list[dict[str, Any]]:
    result = subprocess.run(
        ["php", "artisan", "route:list", "--json"],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    return [route for route in json.loads(result.stdout) if route["uri"].startswith("api/")]


def normalize_path(path: str) -> str:
    return re.sub(r"\{[^}]+}", "{}", path.rstrip("/") or "/")


def relative_api_path(uri: str) -> str:
    parts = uri.split("/", 2)
    return "/" + (parts[2] if len(parts) == 3 else "")


def route_methods(route: dict[str, Any]) -> list[str]:
    return [method.lower() for method in route["method"].split("|") if method != "HEAD"]


def tag_for(path: str) -> str:
    if path.startswith("/auth") or path == "/broadcasting/auth":
        return "Authentication and presence"
    if path.startswith("/device-tokens"):
        return "Device tokens"
    if path.startswith("/admin/users") or path == "/users":
        return "User administration"
    if path.startswith("/admin/audit-logs"):
        return "Audit logs"
    if path.startswith("/admin/products"):
        return "Product administration"
    if path.startswith("/admin/orders"):
        return "Order administration"
    if path.startswith("/admin/returns") or path.startswith("/workspace/returns"):
        return "Return administration"
    if path.startswith("/shopify/proxy/cart"):
        return "Shopify proxy cart"
    if path.startswith("/shopify/proxy/wishlist"):
        return "Shopify proxy wishlist"
    if path.startswith("/shopify/proxy/returns") or path.startswith("/shopify/customer-account"):
        return "Shopify returns"
    if path.startswith("/shopify/webhooks"):
        return "Shopify webhooks"
    if path.startswith("/shop"):
        return "Storefront"
    if path.startswith("/workspaces"):
        return "Workspaces"
    if path.startswith("/workspace/projects"):
        return "Projects"
    if path.startswith("/workspace/tasks") or "time-entr" in path:
        return "Tasks and time tracking"
    if path.startswith("/workspace/conversations"):
        return "Conversations"
    if path.startswith("/workspace/notifications"):
        return "Notifications"
    return "Core"


def access_for(route: dict[str, Any], path: str) -> tuple[str, list[dict[str, list[str]]]]:
    middleware = " ".join(route.get("middleware", []))
    if "VerifyShopifyProxySignature" in middleware or path.startswith("/shopify/proxy"):
        return "Shopify signed request", [{"shopifyProxySignature": []}]
    if path.startswith("/shopify/webhooks"):
        return "Shopify HMAC", [{"shopifyWebhookHmac": []}]
    if "EnsureUserIsAdmin" in middleware:
        return "Administrator", [{"bearerAuth": []}]
    if "EnsureUserIsStaff" in middleware:
        return "Active administrator or employee", [{"bearerAuth": []}]
    if "Authenticate:sanctum" in middleware:
        if "VerifyCustomerOwnership" in middleware:
            return "Authenticated customer owner", [{"bearerAuth": []}]
        if "VerifyConversationParticipant" in middleware:
            return "Authenticated conversation participant", [{"bearerAuth": []}]
        return "Authenticated user", [{"bearerAuth": []}]
    return "Public", []


def operation_id(route: dict[str, Any], method: str) -> str:
    action = route["action"].replace("\\", "_").replace("@", "_")
    action = re.sub(r"[^A-Za-z0-9_]+", "_", action).strip("_")
    path = re.sub(r"[^A-Za-z0-9]+", "_", relative_api_path(route["uri"])).strip("_")
    return f"{method}_{action}_{path}".lower()


def human_summary(route: dict[str, Any], method: str, path: str) -> str:
    action = route["action"].split("@")[-1]
    words = re.sub(r"(?<!^)(?=[A-Z])", " ", action).replace("__invoke", "search").lower()
    noun = tag_for(path).lower()
    verbs = {
        "index": "List", "show": "Get", "store": "Create", "update": "Update",
        "destroy": "Delete", "me": "Get current", "start": "Start", "stop": "Stop",
        "approve": "Approve", "reject": "Reject", "ping": "Refresh", "active": "Get active",
    }
    prefix = verbs.get(action, words.capitalize())
    return f"{prefix} {noun}" if prefix.lower() not in noun else prefix


def path_parameters(path: str) -> list[dict[str, Any]]:
    parameters = []
    for name in re.findall(r"\{([^}]+)}", path):
        numeric = name.lower().endswith("id") or name in {"workspace", "user"}
        parameters.append({
            "name": name,
            "in": "path",
            "required": True,
            "schema": {"type": "integer", "minimum": 1} if numeric else {"type": "string"},
        })
    return parameters


def generated_operation(route: dict[str, Any], method: str, path: str) -> dict[str, Any]:
    access, security = access_for(route, path)
    success = "204" if method == "delete" else ("201" if method == "post" else "200")
    operation: dict[str, Any] = {
        "tags": [tag_for(path)],
        "summary": human_summary(route, method, path),
        "description": f"Access: **{access}**. Implemented by `{route['action']}`.",
        "operationId": operation_id(route, method),
        "responses": {
            success: {"description": "Successful response"},
            "422": {"$ref": "#/components/responses/ValidationError"},
        },
    }
    operation["security"] = security
    parameters = path_parameters(path)
    if parameters:
        operation["parameters"] = parameters
    request_schema = documented_request_schema(method, path)
    if request_schema is not None:
        schema, required = request_schema
        if required:
            schema["required"] = required
        operation["requestBody"] = {
            "required": bool(required),
            "content": {"application/json": {"schema": schema}},
        }
    elif method in {"post", "put", "patch"} and (method, path) not in NO_BODY_OPERATIONS:
        operation["requestBody"] = {
            "required": False,
            "content": {
                "application/json": {
                    "schema": {
                        "type": "object",
                        "description": "Request fields are constrained by the controller or Form Request validation rules.",
                        "additionalProperties": True,
                    }
                }
            },
        }
    return operation


def source_operations() -> tuple[list[dict[str, Any]], dict[str, Any]]:
    specs = []
    components: dict[str, Any] = {"schemas": {}, "responses": {}, "securitySchemes": {}}
    for source in SOURCE_SPECS:
        with source.open(encoding="utf-8") as handle:
            spec = yaml.safe_load(handle)
        specs.append(spec)
        for section, values in spec.get("components", {}).items():
            components.setdefault(section, {}).update(copy.deepcopy(values or {}))
    return specs, components


def matching_source_operation(
    specs: list[dict[str, Any]], path: str, method: str
) -> tuple[dict[str, Any] | None, str | None]:
    for spec in specs:
        for source_path, path_item in spec.get("paths", {}).items():
            if normalize_path(source_path) == normalize_path(path) and method in path_item:
                return copy.deepcopy(path_item[method]), source_path
    return None, None


def align_parameters(operation: dict[str, Any], source_path: str | None, target_path: str) -> None:
    if source_path is None:
        return
    old_names = re.findall(r"\{([^}]+)}", source_path)
    new_names = re.findall(r"\{([^}]+)}", target_path)
    rename = dict(zip(old_names, new_names))
    for parameter in operation.get("parameters", []):
        if parameter.get("in") == "path" and parameter.get("name") in rename:
            parameter["name"] = rename[parameter["name"]]


def build_spec(routes: list[dict[str, Any]]) -> dict[str, Any]:
    specs, components = source_operations()
    components.setdefault("securitySchemes", {}).update({
        "bearerAuth": {"type": "http", "scheme": "bearer", "bearerFormat": "Sanctum token"},
        "shopifyProxySignature": {"type": "apiKey", "in": "query", "name": "signature"},
        "shopifyWebhookHmac": {"type": "apiKey", "in": "header", "name": "X-Shopify-Hmac-Sha256"},
    })
    components.setdefault("schemas", {}).setdefault("Error", {
        "type": "object",
        "required": ["message"],
        "properties": {"message": {"type": "string"}, "errors": {"type": "object", "additionalProperties": True}},
    })
    components.setdefault("responses", {})["ValidationError"] = {
        "description": "The request failed validation.",
        "content": {"application/json": {"schema": {"$ref": "#/components/schemas/Error"}}},
    }

    paths: dict[str, Any] = {}
    for route in routes:
        path = relative_api_path(route["uri"])
        path_item = paths.setdefault(path, {})
        for method in route_methods(route):
            operation, source_path = matching_source_operation(specs, path, method)
            if operation is None:
                operation = generated_operation(route, method, path)
            else:
                align_parameters(operation, source_path, path)
                operation.setdefault("tags", [tag_for(path)])
                operation.setdefault("summary", human_summary(route, method, path))
                operation["operationId"] = operation_id(route, method)
                access, security = access_for(route, path)
                description = operation.get("description", "").rstrip()
                operation["description"] = f"{description}\n\nAccess: **{access}**.".strip()
                operation["security"] = security
            path_item[method] = operation

    components.get("schemas", {}).pop("Wishlist_Item", None)
    used_tags = {
        tag
        for path_item in paths.values()
        for operation in path_item.values()
        for tag in operation.get("tags", [])
    }
    tags = [{"name": name, "description": f"Operations for {name.lower()}."} for name in sorted(used_tags)]
    return {
        "openapi": "3.0.3",
        "info": {
            "title": "Lumi Backend API",
            "version": "1.0.0",
            "description": "Canonical contract for the routes registered under `/api/v1`. See the maintenance documentation for architecture and workflows.",
        },
        "servers": [{"url": "/api/v1", "description": "API path relative to the deployed backend origin"}],
        "tags": tags,
        "paths": dict(sorted(paths.items())),
        "components": components,
    }


def build_route_inventory(routes: list[dict[str, Any]]) -> str:
    groups: dict[str, list[tuple[str, str, str, str]]] = {}
    for route in routes:
        path = relative_api_path(route["uri"])
        access, _ = access_for(route, path)
        group = tag_for(path)
        for method in route_methods(route):
            groups.setdefault(group, []).append((method.upper(), path, access, route["action"]))

    lines = [
        "# Route inventory",
        "",
        "This page is generated from `php artisan route:list --json`. The canonical OpenAPI document contains request and response details.",
        "",
        f"Registered API operations: **{sum(len(items) for items in groups.values())}**.",
        "",
    ]
    for group in sorted(groups):
        lines.extend([f"## {group}", "", "| Method | Path | Access | Handler |", "|---|---|---|---|"])
        for method, path, access, action in sorted(groups[group], key=lambda item: (item[1], item[0])):
            lines.append(f"| `{method}` | `{path}` | {access} | `{action}` |")
        lines.append("")
    return "\n".join(lines)


def main() -> None:
    routes = load_routes()
    spec = build_spec(routes)
    OUTPUT.write_text(yaml.safe_dump(spec, sort_keys=False, allow_unicode=True, width=120), encoding="utf-8")
    ROUTES_OUTPUT.write_text(build_route_inventory(routes), encoding="utf-8")
    print(f"Wrote {OUTPUT.relative_to(ROOT)} with {sum(len(route_methods(r)) for r in routes)} operations")


if __name__ == "__main__":
    main()
