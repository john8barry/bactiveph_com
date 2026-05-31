# How to Prompt Antigravity (Gemini 3 Pro, High) on B Active
A short, practical cheatsheet. Pairs with the hard rules in `.antigravity/rules/bactive-hard-guardrails.mdc`.

> **Why this exists:** the model is capable and fast, but on this project it has repeatedly (a) over-claimed success without proof, (b) worked on production thinking it was safe, and (c) invented results (a "staging" site that didn't exist). Good prompting fixes most of this by forcing *plan → small steps → proof*.

---

## The 5 habits (use every time)

**1. Make it plan and map data BEFORE it codes.**
Gemini follows explicit structure well. For any batch task, ask for the plan first:
> "Before writing any code, list each product and the exact features/price you'll set. Show me the table and WAIT for my OK."
This catches errors (wrong features, wrong prices) before they multiply.

**2. Chunk the work + force a checkpoint.**
> "Do the 8 dresses only. Stop, show me proof, and wait for review before the skorts."
Never let it do 18 things and report at the end.

**3. Demand evidence, not a summary. ("Show me, don't tell me.")**
This is the most important one for this agent.
> "Report the RAW output — the actual command result, the error-log tail with timestamps, the HTTP status, a screenshot of the REAL page. 'Done' means you pasted proof I can check. A screenshot of a 404 is a failure, not a completion."

**4. Make failure reportable.**
> "If any step failed or you couldn't verify it, label it FAILED or UNVERIFIED. Do not fold a failure into a success summary."
(This is exactly how "security secured" hid a failed FTP-password rotation, and "staging complete" hid a staging site that didn't exist.)

**5. Pin the environment every time.**
> "Confirm first: are you on staging or production? What URL, what Site URL, which database? Do not proceed on production."

---

## Gemini-specific tips (from Google's prompting guidance)
- **Clear, specific, structured instructions** beat long prose. Numbered steps work best.
- **Give examples (few-shot).** When you want a format, show one filled-in example and say "match this exactly." (We do this with the product-copy JSON.)
- **Provide context, don't assume it.** Point to the exact file: "use the prices in B_Active_Product_&_Pricing_Master.xlsx," not "use the right prices."
- **Decompose** complex jobs into chained prompts — one outcome per prompt.
- **State the stop condition.** Tell it when to stop and hand back ("…then stop and wait"), or it will keep going and call running-out-of-turn "done."
- **Ask for a rollback note** on anything that changes the server.

---

## Copy-paste preamble (paste at the top of any build task)
```
Before acting: confirm you've read .antigravity/rules/bactive-hard-guardrails.mdc and state
the environment (staging vs production, URL, Site URL, which DB). 
Rules for THIS task:
- Backup exists before any change (no backup, no change).
- Staging only. No destructive FTP on production. blocksy-child only.
- Plan first: show me what you'll change and wait for my OK before a batch.
- Work in small chunks with a STOP for review between them.
- Prove every step with RAW output (commands, logs with timestamps, HTTP status, real-page
  screenshots). A 404 screenshot is a failure. Label anything FAILED/UNVERIFIED honestly.
- Don't invent data; pull products/prices/copy from the source-of-truth files. Mark unknowns [confirm].
End with: What changed · Proof · Unverified/failed · Rollback · Memory updated.
```

## Phrases that work
- "Show me the raw output, not a summary."
- "Plan and map the data first; wait for my approval."
- "Do only X, then stop."
- "Prove it renders at the real URL, not just that rows exist in the DB."
- "If it failed, say FAILED."
- "Are you on staging or production right now?"

## Phrases to avoid (too open-ended for this agent)
- "Set everything up." / "Finish the catalog." / "Make it look good." → it will over-reach and over-claim. Scope it and demand proof instead.
