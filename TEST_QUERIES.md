# SemantiQ — Test Queries (Luma Demo Catalog)

This document provides ready-to-use search queries to demonstrate and validate semantic search behaviour against the standard Luma sample catalog. The queries are grouped to show what semantic search does that keyword search cannot.

Run each query against the storefront search bar, or use the OpenSearch predict endpoint directly (see [Direct API testing](#direct-api-testing)).

---

## Catalog Overview

The Luma demo catalog contains the following product categories relevant to these tests:

| Category | Example Products |
|---|---|
| Bags | Joust Duffle Bag, Crown Summit Backpack, Wayfarer Messenger Bag, Voyage Yoga Bag, Overnight Duffle |
| Fitness Equipment | Affirm Water Bottle, Zing Jump Rope, Quest Lumaflex Band, Sprite Foam Roller, Sprite Stasis Ball, Dual Handle Cardio Ball, Go-Get'r Pushup Grips |
| Yoga | Voyage Yoga Bag, Sprite Yoga Straps (6/8/10 ft), Sprite Foam Yoga Brick, Sprite Yoga Companion Kit |
| Watches | Endurance Watch, Aim Analog Watch, Dash Digital Watch, Bolo Sport Watch, Cruise Dual Analog Watch |
| Tops | Chaz Kangeroo Hoodie, Teton Pullover Hoodie, Bruno Compete Hoodie |

---

## 1. Use-Case / Intent Queries

These queries describe a goal or situation rather than a product name. A keyword search returns zero results; semantic search should surface the relevant products.

| Query | Expected Results | Why it works |
|---|---|---|
| `carry things to the gym` | Joust Duffle Bag, Impulse Duffle, Driven Backpack | "carry" + "gym" maps to bags/duffles |
| `stay hydrated during a workout` | Affirm Water Bottle | "hydrated" semantically linked to water bottle |
| `something for my daily commute` | Wayfarer Messenger Bag, Rival Field Messenger, Push It Messenger Bag | "commute" maps to messenger / shoulder bags |
| `travel light for one night` | Overnight Duffle, Impulse Duffle | "one night" and "travel light" link to overnight/duffle bags |
| `warm up before a run` | Sprite Foam Roller, Quest Lumaflex Band, Zing Jump Rope | warm-up routine maps to fitness prep equipment |
| `recover after leg day` | Sprite Foam Roller, Sprite Foam Yoga Brick | muscle recovery maps to foam roller / yoga props |
| `improve my flexibility` | Sprite Yoga Straps, Sprite Foam Yoga Brick, Sprite Foam Roller | flexibility → stretching equipment |
| `tell time while exercising` | Endurance Watch, Bolo Sport Watch, Aim Analog Watch | exercise + timekeeping → sport watches |
| `build core strength at home` | Dual Handle Cardio Ball, Sprite Stasis Ball, Quest Lumaflex Band | core training → balls and resistance bands |
| `keep my hands free on a hike` | Crown Summit Backpack, Fusion Backpack, Endeavor Daytrip Backpack | hands-free outdoor movement → backpacks |
| `carry a laptop and documents` | Push It Messenger Bag, Rival Field Messenger, Wayfarer Messenger Bag | laptop/documents → messenger bags |
| `gift for someone who loves yoga` | Sprite Yoga Companion Kit, Voyage Yoga Bag, Sprite Yoga Straps | yoga context → yoga-specific products |
| `something to do cardio without a gym` | Zing Jump Rope, Dual Handle Cardio Ball, Quest Lumaflex Band | home cardio → portable fitness equipment |

---

## 2. Synonym Queries

These use different words with the same meaning. A keyword search fails because the words do not appear in any product name; semantic search maps them to the right products.

| Query | Expected Results | Synonym Mapped |
|---|---|---|
| `knapsack` | Crown Summit Backpack, Fusion Backpack, Driven Backpack | knapsack → backpack |
| `rucksack` | Crown Summit Backpack, Endeavor Daytrip Backpack | rucksack → backpack |
| `sweatshirt` | Chaz Kangeroo Hoodie, Teton Pullover Hoodie | sweatshirt → hoodie |
| `pullover` | Teton Pullover Hoodie, Chaz Kangeroo Hoodie | pullover → hoodie |
| `exercise bands` | Quest Lumaflex Band, Pursuit Lumaflex Tone Band, Harmony Lumaflex Strength Band Kit | exercise bands → Lumaflex bands |
| `resistance band` | Quest Lumaflex Band, Pursuit Lumaflex Tone Band | resistance band → Lumaflex band |
| `gym bag` | Joust Duffle Bag, Impulse Duffle, Strive Shoulder Pack | gym bag → duffle/shoulder bag |
| `handbag` | Savvy Shoulder Tote, Compete Track Tote | handbag → tote/shoulder bag |
| `wristwatch` | Aim Analog Watch, Endurance Watch, Dash Digital Watch | wristwatch → watch |
| `skipping rope` | Zing Jump Rope | skipping rope → jump rope |
| `exercise ball` | Sprite Stasis Ball (55/65/75 cm), Dual Handle Cardio Ball | exercise ball → stasis/cardio ball |
| `stability ball` | Sprite Stasis Ball | stability ball → stasis ball |
| `tote` | Compete Track Tote, Savvy Shoulder Tote | tote = tote (partial keyword match + semantic context) |
| `satchel` | Rival Field Messenger, Wayfarer Messenger Bag | satchel → messenger bag |

---

## 3. Conceptual / Multi-Category Queries

These queries span multiple categories or use abstract concepts. They demonstrate that the model understands product purpose, not just product names.

| Query | Expected Results |
|---|---|
| `yoga practice essentials` | Voyage Yoga Bag, Sprite Yoga Companion Kit, Sprite Yoga Straps, Sprite Foam Yoga Brick, Sprite Foam Roller |
| `outdoor adventure gear` | Crown Summit Backpack, Endeavor Daytrip Backpack, Wayfarer Messenger Bag |
| `women's fitness accessories` | Compete Track Tote, Voyage Yoga Bag, Sprite Stasis Ball, Pursuit Lumaflex Tone Band |
| `men's gym essentials` | Joust Duffle Bag, Strive Shoulder Pack, Quest Lumaflex Band, Zing Jump Rope |
| `sporty timepiece` | Endurance Watch, Bolo Sport Watch, Dash Digital Watch |
| `home workout without a gym membership` | Zing Jump Rope, Quest Lumaflex Band, Dual Handle Cardio Ball, Go-Get'r Pushup Grips |
| `stretching and recovery tools` | Sprite Foam Roller, Sprite Yoga Straps, Sprite Foam Yoga Brick |
| `pack light for the weekend` | Overnight Duffle, Crown Summit Backpack, Fusion Backpack |

---

## 4. Natural Language / Conversational Queries

These mimic how shoppers naturally phrase requests. Keyword search breaks on any of these; semantic search should handle them gracefully.

| Query | Expected Results |
|---|---|
| `what should I use to stretch after running` | Sprite Foam Roller, Sprite Yoga Straps, Sprite Foam Yoga Brick |
| `I need something to carry my water to the gym` | Affirm Water Bottle, Joust Duffle Bag |
| `something comfortable to wear while working out` | Chaz Kangeroo Hoodie, Teton Pullover Hoodie |
| `I want to get fit at home with no equipment` | Zing Jump Rope, Quest Lumaflex Band, Go-Get'r Pushup Grips |
| `a bag I can take from the office to the gym` | Strive Shoulder Pack, Rival Field Messenger, Push It Messenger Bag |
| `I need to track my workout time` | Endurance Watch, Bolo Sport Watch |

---

## 5. Negative Test Cases

These should return **no results** (or very low similarity scores). If they surface products, the model may be over-fitting or the similarity threshold is too permissive.

| Query | Expected Results |
|---|---|
| `motorcycle helmet` | No results |
| `coffee maker` | No results |
| `dog leash` | No results |
| `car insurance` | No results |
| `sofa cushion` | No results |

If any of these return results, lower the similarity threshold in **Stores → Configuration → Rivicore → SemantiQ → Max Search Results** or adjust the `minimum_should_match` parameter in the OpenSearch index settings.

---

## 6. Keyword vs Semantic Comparison

Run these pairs side-by-side with SemantiQ enabled and disabled to show the difference clearly.

| Keyword (fails without semantic) | Semantic equivalent that works |
|---|---|
| `knapsack` → 0 results | `backpack` → Crown Summit, Fusion, Driven Backpack |
| `sweatshirt` → 0 results | `hoodie` → Chaz Kangeroo, Teton Pullover |
| `resistance band` → 0 results | `lumaflex` → Quest, Pursuit, Harmony Bands |
| `carry things to gym` → 0 results | `bag` → multiple bag results |
| `wristwatch` → 0 results | `watch` → all watches |

---

## Direct API Testing

You can test the embedding model directly without going through the storefront.

### Test via OpenSearch ML Commons predict

```bash
POST /_plugins/_ml/models/<model_id>/_predict
{
  "text_docs": ["carry things to the gym"]
}
```

The response returns a float array. A non-empty array confirms the model is deployed and producing vectors.

### Test via SemantiQ kNN search

```bash
# 1. Get the vector for a query
POST /_plugins/_ml/models/<model_id>/_predict
{
  "text_docs": ["yoga practice essentials"]
}

# 2. Use the returned vector to search the SemantiQ index
POST /semantiq_vectors/_search
{
  "size": 5,
  "query": {
    "knn": {
      "vector": {
        "vector": [<paste float array here>],
        "k": 5
      }
    }
  }
}
```

The `_source.payload` field in each hit contains the product entity ID and store ID. Cross-reference with `catalog_product_entity` to get SKUs.

### Batch similarity check (MySQL)

After indexing, confirm document counts per entity type:

```sql
SELECT entity_type, store_id, COUNT(*) AS indexed_docs
FROM rivicore_semantiq_index
GROUP BY entity_type, store_id
ORDER BY store_id, entity_type;
```

A healthy index should show one row per store view for `product` and `cms_page`.

---

## Expected Similarity Score Ranges

These are approximate cosine similarity scores when using `sentence-transformers/all-MiniLM-L6-v2`. Other models will differ.

| Query type | Typical score range | Notes |
|---|---|---|
| Exact keyword match | 0.90 – 1.00 | Query text appears in the indexed document |
| Close synonym | 0.75 – 0.90 | e.g. "knapsack" vs "backpack" |
| Use-case / intent | 0.55 – 0.75 | e.g. "stay hydrated" vs "water bottle" |
| Weak conceptual link | 0.40 – 0.55 | Results may be marginally relevant |
| Unrelated (negative) | < 0.35 | Should not appear in results |

Set the OpenSearch `min_score` parameter (or the SemantiQ threshold config) to `0.40`–`0.45` to filter weak matches while keeping useful intent-based results.
