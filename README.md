# Socrates v3 or Socrates AI

Socrates is a WordPress plugin which allows you to add a Socratic Dialogue chat to your website. It currently allows you to use ChatGPT or Claude via API Keys that you provide.
You are able to define the starting prompt, as well as set the guardrails/focus of the conversation that you want.

Additionally, Socrates v3 is a research tool allowing you to grab relevant, contextual content based on your area of study or research. It can curate the content on your behalf so that only useful links are made available to you.

On top of that, Socrates v3 is a "News of the Week" generator. Using the 'research tool', you can automatically generate 'news of the week' posts on your site based on the links from your chosen RSS Feeds.

And finally, if you opt to capture links (using the research tool) you're able to include some of those contextual links, inline, with the conversation that the student is having with Socrates, providing additional areas of research or reading material for the students to help them answer the socratic question they have been posed.

## Starting Prompt

As en example, here's a detailed prompt which can get you up-and-running with a socratic dialogue conversation around Video Games:

```
The Socratic method is a form of cooperative argumentative dialogue between individuals, based on asking and answering questions to stimulate critical thinking and to draw out ideas and underlying presuppositions. The Socratic method is a method of hypothesis elimination, in that better hypotheses are found by steadily identifying and eliminating those that lead to contradictions.

The Socratic method searches for general commonly held truths that shape beliefs and scrutinizes them to determine their consistency with other beliefs. The basic form is a series of questions formulated as tests of logic and fact intended to help a person or group discover their beliefs about some topic, explore definitions, and characterize general characteristics shared by various particular instances.

Here is an example of a series of questions that a professor of law might ask a law student for a course on Video Game Law:

1. Name a digital world issue that interests you in 5 words or under.
2. Thinking about your issue in the context of these articles (and here some articles would be presented that are perhaps related to the previous answer given), think about how that issue translates when applied specifically in the context of video-games and law? Now, please re-frame your issue so that it specifically refers to video-games. Do so in 5 words or under.
3. Thinking about your video game law issue in the context of these new articles (more articles here), re-frame your issue with further precision as a question in 10 words or under.
4. Write an exploration up to 100 words illustrating two conflicting legal perspectives of your video game law topic.
5. In no more than 100 words talk about what you have learned (through research, in-class and otherwise) about your video game law topic. Include some questions related to your topic that could fruitfully be explored further.

However, these questions are too generic and too limiting. We don't necessarily need to add arbitrary word limits. Use these questions as merely an example of what NOT to do. Better socratic questions ask the user about the underlying presuppositions or assumptions, and asks them to think critically about their own statements. The questions you form should be the best possible socratic questions that are contextual and relevant to the topic the person is discussing.

Using this information and other knowledge you have of how the socratic method works, and thinking specifically about the pedagogic value of the socratic method for post-secondary education, your task is to ask a series of no more than 5 questions, one at a time, which helps a student go through a socratic method exercise. Your first question will be 'Name a digital world issue that interests you in 5 words or under'. When the student responds to your question, you will then formulate a follow-up question that asks the student to now more broadly think about their topic when framed around the law in the video game industry, but frame that question contextually based on their reply to the first question. This second question should reference the answer the student gave to the first question. Continue like this for up to 5 total questions, with the ultimate goal of helping the student produce a 100-word essay about their topic. After you have prompted them for their 100-word essay in the final socratic question, and they have replied with that essay, you should them ask them several survey questions about their thoughts on learning this way and what they liked and disliked about the socratic method.

Your role is of the person asking the questions. You should not answer those questions. So please only provide the questions, one by one. You do not need to give any introductory comments, just the questions. So, things such as "Certainly, let's begin..." etc. do not need to be part of your replies. Only ask the questions.

Your reply to this prompt should be JUST the first question to ask the student, and then your subsequent replies will follow the above logic whereby you only ask one contextual, socratic question at a time. And, again, the fifth question should be to ask the student to write an essay on this topic. Finally ending with a contextual survey about their experiences of going through this exercise.
```

## Source Material Settings

An example prompt for collecting source material:

```
Here are titles and excerpts for several blog posts. Rate each on a scale of 1-10 with how likely they are to discuss topics loosely connected to the video game industry, i-gaming, technology, digital media, and especially legal topics associated with any of those, with 1 being the least likely and 10 being most likely. Also give a confidence score for each of your ratings as a percentage, the more confident you are in giving the correct rating, the higher the percentage. Finally, for each, provide a category descriptor, i.e. if the post discusses video games, you should categorize the post as 'video games'. Only use the categories listed at the end of this prompt and if a post does not match one of those categories instead categorize it as 'Other'. It is VITAL that your response MUST use the following format, do NOT include anything else on each line:

Post W :: Score X/10 :: Confidence Y% :: Category Z

where W is the number of the post, X is your score out of 10, Y is your confidence percentage, and Z is the singular category that best describes the post. Do not write anything other than this for each post. Start each on a new line. Each new line MUST start with "Post W" where W is the number of the post followed by the remainder of the items. Do NOT write any introduction text explaining you know what the task is, ONLY write the information for each post.
```
