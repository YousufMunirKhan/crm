# Today's Report – Generate report (GPT)

## When does the "Generate report" button show?

The button **only appears** when you are **logged in** with one of these emails:

- **yousufmunir59@gmail.com**
- **owaishameed301@gmail.com**

So: open Today's Report → if you're logged in with one of these two accounts, you see the **"Generate report"** button next to the date. Any other user (other emails or roles) does **not** see the button, and the API returns 403 if they try to call it.

---

## What prompt we send to GPT

We call the **OpenAI Chat API** with two messages.

### 1. System message (instruction to GPT)

```
You are a concise business report writer. Given raw daily CRM data (team activities, leads, follow-ups, tickets, appointments), produce a clear, professional daily summary in plain text. Use short paragraphs and bullet points where helpful. Do not invent data; only summarize what is provided.
```

### 2. User message (the data we send)

Starts with:

```
Create a concise daily report from this data:

```

Then we append the **raw report text** we build from your CRM for the selected date. That text looks like this (example structure):

```
Daily Report for Thursday, 26 February 2025

--- John Smith (Sales) ---
Follow-ups due: 3, Leads created: 2, Leads won: 1
Attendance: 09:00 - 17:30 (8.5h)
Activities logged: 4
  - Called prospect ABC
  - Sent quote to customer XYZ
  - ...
Lead messages/notes: 5, Appointments: 2
  [note] Follow up next week
  [appointment] Meeting at 2pm
  ...

--- Jane Doe (CallAgent) ---
...
(one block per team member: Sales, CallAgent, Support)

--- TICKETS ---
Total tickets (created/updated this day): 3
  #TKT-001 Subject here - open (Assignee: John)
  #TKT-002 Another - resolved (Assignee: Jane)
  ...
```

So the **full user message** is:  
`"Create a concise daily report from this data:\n\n" + that raw text`.

---

## API call we make

- **URL:** `POST https://api.openai.com/v1/chat/completions`
- **Model:** `gpt-4o-mini`
- **Messages:** system message above + user message above (with the raw report for the chosen date)
- **max_tokens:** 1500

We use your **OPENAI_API_KEY** from `.env` as Bearer token.

---

## Summary

- **Button:** Only when logged in as **yousufmunir59@gmail.com** or **owaishameed301@gmail.com**.
- **Prompt:** System = “write a concise daily summary from this CRM data”; User = “Create a concise daily report from this data:” + raw text (per-employee activities, leads, follow-ups, attendance, lead messages/appointments, then tickets).
