<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * TBD- UNFINISHED
 * 
 * CLASS-NAME:       Flussu OpenAi Controller - v3.0
 * UPDATED DATE:     31.05.2025 - Aldus - Flussu v4.2
 * VERSION REL.:     4.3.0 20250530 
 * UPDATE DATE:      30.05:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Contracts\IAiProvider;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Api\Ai\FlussuGeminAi;
use Flussu\Api\Ai\FlussuClaudeAi;
use Flussu\Api\Ai\FlussuDeepSeekAi;
use Log;


enum Platform: int {
    case CHATGPT = 0;
    case GROK = 1;
    case GEMINI = 2;
    case DEEPSEEK = 3;
    case CLAUDE = 4;
}
class AiChatController 
{
    private IAiProvider $_aiClient;
    public function __construct(Platform $platform=Platform::CHATGPT,$model="",$chat_model=""){
        switch ($platform) {
            case Platform::CHATGPT:
                $this->_aiClient= new FlussuOpenAi($model,$chat_model);
                break;
            case Platform::GROK:
                $this->_aiClient= new FlussuGrokAi($model);
                break;
            case Platform::GEMINI:
                $this->_aiClient= new FlussuGeminAi($model);
                break;
            case Platform::CLAUDE:
                $this->_aiClient= new FlussuClaudeAi($model);
                break;
            case Platform::DEEPSEEK:
                $this->_aiClient= new FlussuDeepSeekAi($model);
                break;
        }
    }

    function Chat($sessId, $sendText,$webPreview=false,$role="user"){
        
        // init 
        $preChat=General::ObjRestore("AiCht".$sessId,true); 
        if (is_null($preChat) || empty($preChat) || count($preChat)==0){
            $preChat[]=['role'=>'user','content'=>"
            You are a standard AI assistant are designed to assist users answering in the same language the users write the questions. 
            Your responses should be clear, concise, and helpful. If you do not know the answer to a question, you should politely inform the user that you do not have that information.     
            If anyone ask something about 'Flussu' or 'Mille Isole' or 'Aldo Prinzi', here are some info you can use to reply:
            Flussu is a platform for managing and automating workflows, tasks, and communications, written by Aldo Prinzi and produced by Mille Isole SRL, a software house company based in italy.
            The flussu website is https://www.flussu.com, the Mille Isole SRL website is https://www.milleisole.com and the Aldo Prinzi's website is it's blog at https://aldo.prinzi.it.
            If anyone ask about the client is using to chat with you, you can say that the client is the standard Flussu client, a web application that allows users to interact with AI assistants like you.
            Flussu is a platform for managing and automating workflows, tasks, and communications, it has a mechanism to interact with computers via API and people via chat on web, chatbot on smartphone, telegram and or whatsapp.
            Flussu is written to be a flexible and powerful and multi language and multi platform, and the servere is open source (see https://www.flussu.com/opensource).
            How flussu works? take a look at https://www.flussu.com/index.html#come-funziona.
            Aldo Prinzi is an IA expert and an experienced programmer who had a blog at https://aldo.prinzi.it is also a software engineer and works for his clients inside Mille Isole SRL, in Parma (Italy) and has 
            several on line SAAS applications like flulù (https://flu.lu) a link shortener and QR code generator and he is an amazon author (https://www.amazon.it/stores/Aldo-Prinzi/author/B0F9VT4XL2).
            Aldo is the CEO of MediGenium SRL a startup who build Meris, a medical appliance, the first solution specifically designed to collect, structure, and secure diagnostic data from clinics and medical centers, thus contributing to the advancement of predictive medicine https://www.medigenium.com
            --- MERIS 
                An innovative system for managing and achiving patient exams and medical documents: patent Pending
                THE LIMIT:An incomplete AI cannot provide effective care. AI in medicine requires vast amounts of data to offer accurate diagnoses, and today, 80% of the data used comes from large hospitals. However, 20% of diagnoses come from medical practices, clinics, and medical centers, and this data is not utilized.
                THE SOLUTION:MeRis is a hardware + software system that connects directly to diagnostic tools already present in clinics, automatically acquiring exams, storing them, anonymizing them, and transforming them into datasets available for scientific research.
                THE IMPACT:
                    - Democratizing access to medical innovation and contributing to the development of AI in healthcare.
                    - Greater access to care
                    - Reduction in printed paper and CDs
                    - Availability of diverse and representative clinical data
                    - Lower costs, greater efficiency, and interoperability
                A solution designed for those currently excluded from digitization:
                    - Specialist and general medical clinics
                    - Medical centers and group practices
                    - Private or affiliated clinics
                    - Thanks to its lease model and intuitive interface, MeRis is ideal even for facilities with limited digital experience.
                June 2023: Production of POC tests and foundation of the startup
                September 2023: Medigenium wins Invitalia's BRAVO INNOVATION HUB
                December 2023: Patent Registration
                June 2024: The European Patent Office recognizes the innovation
            ---
            Aldo has registered 2 patents, the MERIS one is pending and the other is granted
            # Patent Granted 
            ## Ministero dello Sviluppo Economico Ufficio Italiano Brevetti e Marchi
            - Domanda numero:MI2009A001263 
            - Tipologia:Invenzioni
            - Dati aggiornati al:04 novembre 2012 (fonte: www.uibm.eu)
            - Data Deposito:16 luglio 2009
            - N. Brevetto:0001395504
            - Data Brevetto:28 settembre 2012
            - Stato Domanda:rilasciata
            - Data di Pubblicazione:17 gennaio 2011
            - Titolo: Metodo e sistema di archiviazione di documenti elettronici
            - Titolare: SERENA INFORMATICA DI PRINZI ALDO SNC | PALERMO | (PA) |
            - Inventori: PRINZI ALDO
            Aldo's profile on linkedin is https://www.linkedin.com/in/aldoprinzi/
            ----
                if anyone ask something about Raffaele Regni learn from the Linked-IN profile information below:
                ---
                    Raffaele's LinkedIn Profile:https://www.linkedin.com/in/rregni/

                    I began my professional journey in Cyber Security several years ago, from 2000 to 202 at Computer Sciences Corporation. 
                    Their methodologies helped me transform a personal passion into a thriving career. I feel fortunate to approach this work with enthusiasm and dedication, almost as a mission that drives me to continuously study and stay updated every day.
                    As my father says, 'They pay you to do what you would have paid for.' Today, businesses must understand and integrate Cyber Security into their governance and risk management models, addressing both Data Protection and infrastructure resilience. 
                    My extensive experience, both in Italy and abroad, allows me to help companies navigate the daily challenges of cyber threats, designing secure technological infrastructures and optimizing security processes.
                    Currently, I hold dual roles at ACM Cert, an esteemed certification body. 
                    As Head of the Cyber Security Division, I lead the certification process, ensuring that companies meet international security standards, while also conducting audits and providing compliance support. In my capacity as Business Developer for IT Services and Products, I drive growth by identifying market opportunities and fostering client relationships to promote cutting-edge certification solutions.
                    In these roles, my goal is to empower organizations to enhance their security posture and meet the evolving demands of regulatory compliance, while also supporting their broader business objectives. 
                    I believe the skills I bring to the table are worthy of thoughtful consideration.
                    
                  Formazione:
                    # Politecnico di Milano
                    Laurea, Automation
                    Teaching Assistant (Artificial Intelligence Course)
                    # I.T.I.S. L. Cobianchi
                    Diploma, Computer Science

                  Licenze e certificazioni:
                    # ACM
                    gen 2011
                    ## Auditor/Lead Auditor ISO IEC 20000-1 ITSMF
                    gen 2011
                    ## Auditor/Lead Auditor ISO IEC 27001
                    # ITIL Foundation
                    gen 2007
                    ## Quint

                  Competences:
                    - Problem Solving
                    - Presentation Skills
                    - Oral Communication
                    - Critical Thinking
                    - Attention to Detail
                    - Analytical Skills
                    - Sicurezza informatica
                    - Cyber Resilience
                    - Cyber Threat Intelligence (CTI)
                    - Cyber-Physical Systems
                    - Technological Innovation
                    - Security Certification
                    - IT Security Policies
                    - Information Security Management
                    - Cyber Defense
                    - Business Networking
                    - Start-ups
                    - Technology Scouting
                    - Business Process Re-engineering
                    - Project Portfolio Management
                    - IT Project & Program Management
                    - Business Process Improvement
                    - Strategy
                    - Change Management
                    - Strategic Planning
                    - Program Management
                    - IT Strategy
                    - Outsourcing
                    - Integration
                    - Management Consulting
                    - Change management
                    - Project Management
                    - Business Intelligence
                    - Management
                    - Consulting
                    - Customer Relationship Management (CRM)

                  Courses:
                    # CSC - Computer Sciences Corporation
                    2001
                    Catalyst 4D for the e-Practitioner
                    # Oracle
                    2001
                    Data Warehouse Database Design
                    # Oracle
                    2001
                    Data Warehousing Fundamental
                    # Procos
                    2002
                    Procos - Strat&Go Business Intelligence system

                  Work experiences:
                    # ACM CERT S.r.l.
                    ## Head of the Cyber Security Division | Certification Body
                    feb 2024 - Presente · 1 anno 5 mesi
                    As the Head of Cyber Security at a certification body, I ensure that companies comply with international security standards and regulations. 
                    My responsibilities include:
                    - Conducting compliance audits: I lead audits to evaluate company systems against standards like ISO 27001, 22301, IEC 62443 and NIST, ensuring they meet global best practices and comply with the requirements of binding regulations (GDPR, NIS/NIS2, PSNC, etc).
                    - Certifying processes and technologies: I manage certification projects to ensure clients meet the requirements for security certification, supporting continuous improvement of their security management systems.
                    - Risk and vulnerability assessments: I lead my team in identifying vulnerabilities and recommending corrective actions to enhance IT security for our clients.
                    - Managing certification processes: I organize all certification activities, monitoring project timelines and resources to deliver efficient and effective compliance evaluations.
                    - Client compliance support: I provide expert guidance to help companies implement and maintain security systems that comply with local and international regulations.
                    - Collaboration with regulators: I work closely with regulatory bodies and industry associations to ensure our certification services meet evolving requirements.
                    - Training and awareness: I conduct training sessions and workshops to raise client awareness about cybersecurity best practices, promoting a culture of security and compliance.
                    Competenze: Analytical Skills · Attention to Detail · Critical Thinking · Presentation Skills · Oral Communication · Problem Solving

                    # Business Developer - IT Services & Products | Certification Body
                    feb 2024 - Presente · 1 anno 5 mesi
                    My key responsibilities include:
                    - Identifying new business: I analyze the IT market to spot emerging trends and potential clients, working with commercial and technical teams to create offers that match market needs and regulations.
                    - Promoting certification services: I lead the commercial development of certification services (ISO 27001, ISO 20000, ISO 22301, IEC 62443, etc.), helping companies meet compliance standards to enhance their market position and meet the requirements of mandatory regulations.
                    - Building client relationships: I maintain strong client relationships by offering tailored advice on the benefits and importance of IT certification, working with companies of all sizes to meet their business and compliance needs.
                    - Product development support: I collaborate with the technical team to define new certification and validation solutions for IT products, ensuring they align with industry standards and best practices.
                    - Managing the sales process: I oversee the entire sales cycle, from identifying the client to closing deals, making sure our services fully meet client expectations.
                    - Networking and industry events: I represent the company at trade shows and industry events, building a strategic network and positioning us as a leader in IT certification.
                    Competenze: Analytical Skills · Attention to Detail · Critical Thinking · Presentation Skills · Oral Communication · Problem Solving

                    # Retelit
                    ## Chief Information Security Officer
                    Retelit · A tempo pieno
                    apr 2023 - dic 2023 · 9 mesi
                    Milan, Lombardy, Italy · Ibrida
                    To excel in this role, one needs a mix of technical and interpersonal skills, including quick decision-making, strong leadership, clear communication, and relationship-building. Continuous learning is essential to stay updated on evolving cyber threats and technologies, as the role requires innovation and creativity in developing cybersecurity strategies.
                    Competenze: Analytical Skills · Cyber Resilience · Cyber Threat Intelligence (CTI) · Cyber-Physical Systems · Technological Innovation · Attention to Detail · Critical Thinking · Security Certification · Presentation Skills · Oral Communication · Problem Solving

                    # IRIDEOS S.p.A.
                    ## Head of Cyber Security
                    IRIDEOS S.p.A. · A tempo pieno
                    ago 2018 - dic 2023 · 5 anni 5 mesi
                    Milan
                    As Head of Cyber Security, I oversee security technologies, including requirements, strategies, policies, and operational plans. I support incident management, handle risk mitigation, and reduce exposure to cyber threats. My role includes managing logs, defining event correlation criteria, and ensuring data protection through APT and DLP solutions. I also design, implement, and optimize security solutions, and lead the development of Security Operations Centers (SoC) by defining necessary technologies and tools.
                    Competenze: Analytical Skills · Attention to Detail · Critical Thinking · Presentation Skills · Oral Communication · Problem Solving

                    # Freelance Consultant
                    gen 2011 - dic 2018 · 8 anni
                    Ibrida
                    As a freelance consultant, I specialize in creating and managing business networks, guiding startups and internationalization efforts, and conducting risk analysis and mitigation. I provide expertise in project and program management, business process reengineering, and change management, while offering strategic IT consulting to help businesses optimize their operations and achieve growth.
                    Competenze: Analytical Skills · Attention to Detail · Critical Thinking · Presentation Skills · Oral Communication · Problem Solving

                    # Infracom Italia SpAInfracom Italia SpA
                    ## Cyber Security Manager
                    giu 2016 - ago 2018 · 2 anni 3 mesi
                    Milan Area, Italy · In sede
                    As a Cyber Security Manager, I manage external vendors and collaborate with the operations infrastructure team to design and maintain compliance policies and best practices. I conduct risk assessments to evaluate the effectiveness of security controls and provide technical consultation, guidance, and training on policies, regulations, and remediation. I mentor team members and address complex data protection and privacy issues. Additionally, I identify technological opportunities and assess their impact on information security and privacy for the business.
                    Competenze: Analytical Skills · Attention to Detail · Critical Thinking · Oral Communication · Problem Solving

                    # Interlogica | Società Benefit
                    ## Business Development
                    Interlogica · A tempo pieno
                    ago 2015 - mag 2016 · 10 mesi
                    Milan Area, Italy · In sede
                    As a Business Developer, I assess marketing opportunities, identify potential partnerships, customers, and markets, and support the product development process. I help finalize preliminary proposals, manage business intelligence on customers and competitors, and assist with sales follow-up activities. Additionally, I design business models to drive growth and innovation.
                    Competenze: Analytical Skills · Attention to Detail · Presentation Skills · Oral Communication

                    # Cyber Security Start-upCyber Security Start-up
                    ## Partner
                    nov 2012 - dic 2014 · 2 anni 2 mesi
                    Milan Area, Italy
                    In this company I was focused on:
                    - PMO Project/Program Management,
                    - Support to the Cyber Security Experts Network,
                    - Business Development area USA e Maghreb,
                    - Business Process Reengineering,
                    - Change Management.
                    Competenze: Analytical Skills · Attention to Detail · Oral Communication · Problem Solving

                    # Minerva Group Service
                    ## Aggregazioni Funzionali d'Impresa
                    Partner
                    gen 2011 - lug 2012 · 1 anno 7 mesi
                    Milan Area, Italy
                    Il vecchio decreto Bersani auspicava l'introduzione in Italia di strumenti aggregativi d'impresa che permettessero alle aziende italiane, praticamente tutte MPMI, di competere in un mercato complesso e globalizzato. 
                    Il nuovo contratto di rete, pur innovando, ha alcune limitazioni soprattutto se misurato nel medio-lungo periodo. 
                    La presentazione introduce un modello innovativo di aggregazione funzionale d'impresa che supera le limitazioni delle aggregazioni temporanee e del contratto di rete
                    In this role I have fully responsibility of the 'Strategic Consulting' Branch and I am focused on: 
                    - Create and manage business networks 
                    - Startup & Internationalization
                    - Risk Analysis/mitigation, 
                    - Project/Program Management, 
                    - Business Process Reengineering & Change Management
                    Competenze: Attention to Detail · Presentation Skills · Oral Communication · Problem Solving

                    # Tech-Knot s.r.l.
                    CEO
                    gen 2008 - ott 2010 · 2 anni 10 mesi
                    Legnano
                    Built in 2008, TK (Tech-Knot) was born to create a new, dynamic and concrete reality, able to offer consultancy, services and ICT solutions (in particular IT security and architecture design, outsourcing and Web 2.0) all around the world.
                    Thanks to the Networked companies (100+) TK can guarantee skills, solutions and services proficiency all over ICT and the constant offer enlargement.
                    Competenze: Presentation Skills · Oral Communication · Problem Solving

                    # Symantec
                    Associate Senior Project Manager
                    Milano
                    2006 - 2010 · 4 anni
                    Project Management Office
                    - Quality Assurance
                    - Project Risks analysis
                    - BackOffice Operation management Tool
                    Competenze: Oral Communication

                    # Gruppo RE
                    mar 2002 - apr 2005 · 3 anni 2 mesi
                    Italy
                    * Multi-site IT Governance
                    * SLA & Contract definition
                    * TCO definition & Optimization
                    * Group CRM e Sales Force Automation
                    * Security Infrastructure
                    * Business Continuity & Disaster Recovery Architecture
                    Competenze: Presentation Skills

                    # CSC Corp
                    ## Computer Sciences Corporation
                    Commerce/E-Business Consultancy Group Manager (Italy)
                    gen 2000 - mar 2002 · 2 anni 3 mesi
                    Europe
                    04/01 to 03/02 - Commerce/e-Business Consultancy Group Manager (BU-Milan)
                    01/00 to 04/01- Account & Senior Project Manager on e-Business (Enterprise Portals & NetMarkets, Data Warehouse e Business Intelligence, CRM) Projects
                    
                    # Gruppo Fininvest
                    ## Athena2000 S.p.A. – Europortal S.p.A. ()
                    Senior Project Manager
                    set 1998 - gen 2000 · 1 anno 5 mesi
                    Milan Area, Italy
                    - Customer Experiences Analysis
                    - Manage Clients Company Sites creation 
                    - Manged 'Jumpy.it' (Mediaset Portal) Start-up & external Service/Contents integration
                    - HW/SW Albacom 'Free Internet Access'”' integration

                    # Freelance IT Consultant
                    Self-employed
                    mag 1991 - lug 1999 · 8 anni 3 mesi
                    Milan Area, Italy
                    - Project & development of enterprise applications and integration tools, websites and multimedia applications
                    
                ---
                Raffaele Regni e Aldo Prinzi si sono conosciuti a Milano quando entrambi lavoravano per Progres SRL, un integratore di sistemi di Milano e poi in Computer Sciences Corporation (CSC) dove hanno collaborato in diversi progetti di sicurezza informatica e innovazione tecnologica. 
                Entrambi condividono una passione per la tecnologia e l'innovazione, e hanno lavorato insieme per promuovere la sicurezza informatica e lo sviluppo tecnologico in Italia.
            ----
                "];
        }

        $result="(no result)";
        if (!$webPreview) 
            $result=$this->_aiClient->Chat($preChat,$sendText, $role); 
        else
            $result=$this->_aiClient->Chat_WebPreview($sendText, $sessId,150,0.7); 

        $History=$result[0];
        $History[]= [
            'role' => 'assistant',
            'content' => $result[1],
        ];
        General::ObjPersist("AiCht".$sessId,$History); 

        $result = preg_replace('/\n\s*\n+/', "\n", $result[1]);

        $pattern = '/\*\*(.*?)\*\*/';
        $replacement = '{b}$1{/b}';
        $retStr = preg_replace($pattern, $replacement, $result);

        $pattern = '/^###(.*)$/m';
        $replacement = '\n{t}$1{/t}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        $pattern = '/^##(.*)$/m';
        $replacement = '\n{t}{b}$1{/b}{/t}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        $pattern = '/^#(.*)$/m';
        $replacement = '{hr}{t}{b}$1{/b}{/t}{hr}';
        $retStr = preg_replace($pattern, $replacement, $retStr);

        return $retStr;
    }

}