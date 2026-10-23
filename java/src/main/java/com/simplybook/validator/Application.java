package com.simplybook.validator;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        try {
            TokenAuth.loadSecret();
        } catch (IllegalStateException e) {
            System.err.println(e.getMessage());
            System.exit(1);
        }
        SpringApplication.run(Application.class, args);
    }
}
